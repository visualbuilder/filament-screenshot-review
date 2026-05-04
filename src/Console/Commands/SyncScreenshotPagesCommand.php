<?php

declare(strict_types=1);

namespace Visualbuilder\FilamentScreenshotReview\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Visualbuilder\FilamentScreenshotReview\Models\ScreenshotPage;
use Visualbuilder\FilamentScreenshotReview\Observers\ScreenshotPageObserver;
use Visualbuilder\FilamentScreenshotReview\Services\SitemapWriter;

/**
 * Mirrors the catalogue's sitemap JSON into the screenshot_pages table.
 *
 * For each entry in the JSON we expand the row into one ScreenshotPage per
 * (viewport × mode) combination, so reviewers can include/exclude each
 * variant of a page independently.
 *
 * Pages that disappear from the JSON have `removed_at` set so reviewers can
 * see what fell out without losing the historical record. Pages that
 * reappear have `removed_at` cleared.
 *
 * The `included` flag is preserved on existing rows — once a reviewer
 * decides to exclude a row, regenerating the registry from JSON should not
 * silently re-include it.
 */
class SyncScreenshotPagesCommand extends Command
{
    protected $signature = 'screenshot-review:sync-pages {--panel= : Filament panel key (CLI-friendly form)}';

    protected $description = 'Sync the catalogue sitemap JSON into the screenshot_pages table.';

    public function handle(SitemapWriter $writer): int
    {
        $keys = $this->panelKeys();

        if ($keys === []) {
            $this->error('No panels to sync. Pass --panel=KEY or register descriptors with PanelRegistry::register().');

            return self::FAILURE;
        }

        // The catalogue's `panel:sitemap` mutates Filament's current-panel
        // state. When this command is invoked from a Filament action, that
        // pollution leaks into the Livewire response and breaks any post-
        // action URL resolution. Save the panel context up front and restore
        // it once we're done.
        $previousPanel = class_exists(\Filament\Facades\Filament::class)
            ? \Filament\Facades\Filament::getCurrentPanel()
            : null;

        try {
            $totals = ['added' => 0, 'updated' => 0, 'removed' => 0];

            foreach ($keys as $key) {
                $totals = $this->syncPanel($key, $totals);
            }

            // Suppression keeps the row-by-row observer noise quiet during the
            // upsert; we re-emit a single sitemap once everything's settled.
            ScreenshotPageObserver::withoutSitemapWrites(function () use ($writer, $keys): void {
                foreach ($keys as $key) {
                    $writer->writeForPanel($this->panelInternalId($key));
                }
            });

            $this->info(sprintf(
                'Sync complete — added %d, updated %d, removed %d.',
                $totals['added'],
                $totals['updated'],
                $totals['removed'],
            ));

            return self::SUCCESS;
        } finally {
            if ($previousPanel !== null) {
                \Filament\Facades\Filament::setCurrentPanel($previousPanel);
            }
        }
    }

    /**
     * @return array<int, string>
     */
    private function panelKeys(): array
    {
        if ($key = $this->option('panel')) {
            return [$key];
        }

        if (class_exists(\Visualbuilder\FilamentScreenshotCatalogue\PanelRegistry::class)) {
            return \Visualbuilder\FilamentScreenshotCatalogue\PanelRegistry::keys();
        }

        return [];
    }

    private function panelInternalId(string $key): string
    {
        if (class_exists(\Visualbuilder\FilamentScreenshotCatalogue\Services\ScreenshotConfig::class)) {
            return \Visualbuilder\FilamentScreenshotCatalogue\Services\ScreenshotConfig::panelInternalId($key);
        }

        return $key;
    }

    /**
     * @param  array{added:int,updated:int,removed:int}  $totals
     * @return array{added:int,updated:int,removed:int}
     */
    private function syncPanel(string $key, array $totals): array
    {
        $panelId = $this->panelInternalId($key);

        // Refresh the JSON from Filament introspection first — only do this
        // when the catalogue's command is actually registered, so tests can
        // seed a sitemap fixture directly without invoking the catalogue.
        if ($this->catalogueSitemapAvailable()) {
            Artisan::call('panel:sitemap', ['--panel' => $key], $this->getOutput());
        }

        $jsonPath = $this->jsonPathFor($panelId);
        if (! is_file($jsonPath)) {
            $this->warn("No sitemap JSON found for panel '{$panelId}' at {$jsonPath} — skipping.");

            return $totals;
        }

        /** @var array<int, array<string, mixed>> $entries */
        $entries = json_decode((string) file_get_contents($jsonPath), associative: true) ?: [];

        $viewports = (array) config('screenshot-review.viewports', ['desktop', 'tablet', 'mobile']);
        $modes = (array) config('screenshot-review.modes', ['light', 'dark']);

        $seenSlugs = [];

        ScreenshotPageObserver::withoutSitemapWrites(function () use (
            $entries,
            $key,
            $viewports,
            $modes,
            &$seenSlugs,
            &$totals,
        ): void {
            foreach ($entries as $entry) {
                $slug = (string) ($entry['slug'] ?? '');
                if ($slug === '') {
                    continue;
                }

                $seenSlugs[] = $slug;

                foreach ($viewports as $viewport) {
                    foreach ($modes as $mode) {
                        $existing = ScreenshotPage::query()
                            ->where('panel', $key)
                            ->where('slug', $slug)
                            ->where('viewport', $viewport)
                            ->where('mode', $mode)
                            ->first();

                        if ($existing) {
                            $existing->fill([
                                'url' => (string) ($entry['url'] ?? $existing->url),
                                'label' => $entry['label'] ?? $existing->label,
                                'type' => (string) ($entry['type'] ?? $existing->type ?? 'page'),
                                'auth' => $entry['auth'] ?? null,
                                'sort' => (int) ($entry['sort'] ?? $existing->sort),
                                'removed_at' => null,
                            ])->save();

                            $totals['updated']++;
                        } else {
                            ScreenshotPage::create([
                                'panel' => $key,
                                'slug' => $slug,
                                'viewport' => $viewport,
                                'mode' => $mode,
                                'url' => (string) ($entry['url'] ?? '/'),
                                'label' => $entry['label'] ?? null,
                                'type' => (string) ($entry['type'] ?? 'page'),
                                'auth' => $entry['auth'] ?? null,
                                'sort' => (int) ($entry['sort'] ?? 1000),
                                'included' => true,
                            ]);

                            $totals['added']++;
                        }
                    }
                }
            }

            $removed = ScreenshotPage::query()
                ->where('panel', $key)
                ->whereNotIn('slug', array_unique($seenSlugs) ?: [''])
                ->whereNull('removed_at')
                ->update(['removed_at' => now()]);

            $totals['removed'] += (int) $removed;
        });

        return $totals;
    }

    private function catalogueSitemapAvailable(): bool
    {
        return array_key_exists('panel:sitemap', Artisan::all());
    }

    private function jsonPathFor(string $panelId): string
    {
        $template = (string) config(
            'screenshot-review.sitemap_json_path',
            storage_path('app/sitemap-{panel}.json'),
        );

        return str_replace('{panel}', $panelId, $template);
    }
}
