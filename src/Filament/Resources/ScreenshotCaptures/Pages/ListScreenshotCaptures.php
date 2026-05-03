<?php

declare(strict_types=1);

namespace Visualbuilder\FilamentScreenshotReview\Filament\Resources\ScreenshotCaptures\Pages;

use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;
use Visualbuilder\FilamentScreenshotReview\Enums\ScreenshotStatus;
use Visualbuilder\FilamentScreenshotReview\Filament\Resources\ScreenshotCaptures\ScreenshotCaptureResource;
use Visualbuilder\FilamentScreenshotReview\Models\ScreenshotCapture;

class ListScreenshotCaptures extends ListRecords
{
    protected static string $resource = ScreenshotCaptureResource::class;

    /**
     * One tab per registered Filament panel, plus an "All" tab that shows
     * every capture. Each tab carries a badge with the count of pending
     * captures for that panel so reviewers see at a glance where work is
     * waiting.
     *
     * Falls back to whichever distinct `panel` values appear in the
     * captures table when the catalogue's PanelRegistry isn't loaded —
     * keeps the UI useful even outside the canonical capture+review
     * pipeline.
     *
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        $tabs = [
            'all' => Tab::make('All'),
        ];

        $labels = $this->panelLabels();

        foreach ($this->panelKeys() as $key) {
            $tabs[$key] = Tab::make($labels[$key] ?? \Illuminate\Support\Str::headline($key))
                ->modifyQueryUsing(
                    fn (Builder $query) => $query->whereHas(
                        'screenshotPage',
                        fn (Builder $q) => $q->where('panel', $key),
                    ),
                )
                ->badge($this->pendingCountFor($key))
                ->badgeColor('warning');
        }

        return $tabs;
    }

    /**
     * Map of panel key -> display label, sourced from PanelDescriptor::label()
     * when the catalogue's PanelRegistry is loaded. Returns an empty map when
     * the registry isn't present, in which case getTabs() falls back to a
     * headlined version of the key.
     *
     * @return array<string, string>
     */
    protected function panelLabels(): array
    {
        if (! class_exists(\Visualbuilder\FilamentScreenshotCatalogue\PanelRegistry::class)) {
            return [];
        }

        $labels = [];
        foreach (\Visualbuilder\FilamentScreenshotCatalogue\PanelRegistry::all() as $key => $descriptor) {
            if (method_exists($descriptor, 'label')) {
                $labels[$key] = $descriptor->label();
            }
        }

        return $labels;
    }

    public function getDefaultActiveTab(): string
    {
        return 'all';
    }

    /**
     * @return array<int, string>
     */
    protected function panelKeys(): array
    {
        if (class_exists(\Visualbuilder\FilamentScreenshotCatalogue\PanelRegistry::class)) {
            $registered = \Visualbuilder\FilamentScreenshotCatalogue\PanelRegistry::keys();

            if ($registered !== []) {
                return $registered;
            }
        }

        return ScreenshotCapture::query()
            ->join('screenshot_pages', 'screenshot_pages.id', '=', 'screenshot_captures.screenshot_page_id')
            ->distinct()
            ->orderBy('screenshot_pages.panel')
            ->pluck('screenshot_pages.panel')
            ->all();
    }

    protected function pendingCountFor(string $panelKey): ?int
    {
        $count = ScreenshotCapture::query()
            ->where('status', ScreenshotStatus::PENDING)
            ->whereHas(
                'screenshotPage',
                fn (Builder $q) => $q->where('panel', $panelKey),
            )
            ->count();

        return $count > 0 ? $count : null;
    }
}
