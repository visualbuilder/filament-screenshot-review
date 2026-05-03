<?php

declare(strict_types=1);

namespace Visualbuilder\FilamentScreenshotReview\Services;

use Visualbuilder\FilamentScreenshotReview\Models\ScreenshotPage;

/**
 * Renders the catalogue-shaped sitemap JSON for a single panel based on the
 * current ScreenshotPage state. Called by the observer (after any toggle in
 * the UI) and by the sync-pages command (after a bulk upsert).
 *
 * The output shape mirrors what the catalogue's GeneratePanelSitemapCommand
 * writes, so re-running `panel:sitemap` and re-running this writer should
 * produce structurally compatible files.
 */
class SitemapWriter
{
    public function pathFor(string $panel): string
    {
        $template = (string) config(
            'screenshot-review.sitemap_json_path',
            storage_path('app/sitemap-{panel}.json'),
        );

        return str_replace('{panel}', $panel, $template);
    }

    public function writeForPanel(string $panel): string
    {
        $path = $this->pathFor($panel);

        // Page rows materialise one row per (slug × viewport × mode), but the
        // sitemap JSON is per-slug. Group by slug and emit a single entry per
        // slug — the capture pipeline will fan out per viewport/mode again.
        $entries = ScreenshotPage::query()
            ->where('panel', $panel)
            ->where('included', true)
            ->whereNull('removed_at')
            ->orderBy('sort')
            ->orderBy('slug')
            ->get()
            ->unique(fn (ScreenshotPage $p) => $p->slug)
            ->values()
            ->map(fn (ScreenshotPage $p) => [
                'slug' => $p->slug,
                'label' => $p->label ?? $p->slug,
                'url' => $p->url,
                'type' => 'page',
                'sort' => $p->sort,
            ])
            ->all();

        $directory = dirname($path);
        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        file_put_contents(
            $path,
            json_encode($entries, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n",
        );

        return $path;
    }
}
