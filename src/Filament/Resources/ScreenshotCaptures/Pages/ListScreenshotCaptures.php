<?php

declare(strict_types=1);

namespace Visualbuilder\FilamentScreenshotReview\Filament\Resources\ScreenshotCaptures\Pages;

use Filament\Actions\Action;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Artisan;
use Visualbuilder\FilamentScreenshotReview\Enums\ScreenshotStatus;
use Visualbuilder\FilamentScreenshotReview\Filament\Resources\ScreenshotCaptures\ScreenshotCaptureResource;
use Visualbuilder\FilamentScreenshotReview\Models\ScreenshotCapture;

class ListScreenshotCaptures extends ListRecords
{
    protected static string $resource = ScreenshotCaptureResource::class;

    /**
     * Header action: open a modal with one ToggleButtons control listing
     * every registered panel, then dispatch a `screenshot:dispatch` batch
     * per selection. The captures land on S3 and the catalogue's
     * `finally` callback (5.2.2+) auto-syncs them into screenshot_captures
     * so the grid here updates without a manual sync step.
     */
    protected function getHeaderActions(): array
    {
        return [
            // Live-ish queue indicator. Reads the LLEN of the queue
            // captures land on (configurable via screenshot-catalogue.queue,
            // defaults to "default") via the Redis facade, so the figure
            // refreshes whenever this Livewire component re-renders. Click
            // does nothing useful — it's just a visible badge that
            // reviewers can use as a "should I dispatch more or wait?"
            // signal.
            Action::make('queue_status')
                ->label('Queue')
                ->icon('heroicon-o-queue-list')
                ->color('gray')
                ->disabled()
                ->extraAttributes(['style' => 'opacity: 1;'])
                ->badge(fn (): string => (string) $this->screenshotQueueLength())
                ->badgeColor(fn (): string => $this->screenshotQueueLength() > 0 ? 'warning' : 'gray'),
            Action::make('regenerate')
                ->label('Regenerate captures')
                ->icon('heroicon-o-arrow-path')
                ->color('primary')
                ->modalHeading('Regenerate captures')
                ->modalDescription('Select one or more panels to recapture. Each panel runs as its own background batch — refresh this page in a few minutes to see the new shots.')
                ->modalSubmitActionLabel('Dispatch')
                ->schema([
                    ToggleButtons::make('panels')
                        ->label('Panels')
                        ->options(fn (): array => $this->panelOptionsForRegenerate())
                        ->icons(fn (): array => $this->panelIconsForRegenerate())
                        ->multiple()
                        ->required()
                        ->inline(),
                ])
                ->action(function (array $data): void {
                    $panels = (array) ($data['panels'] ?? []);

                    if ($panels === []) {
                        return;
                    }

                    $succeeded = [];
                    $failed = [];

                    foreach ($panels as $panel) {
                        $exit = Artisan::call('screenshot:dispatch', [
                            '--panel' => $panel,
                        ]);

                        if ($exit === 0) {
                            $succeeded[] = $panel;
                        } else {
                            $failed[] = $panel . ' (' . trim(Artisan::output()) . ')';
                        }
                    }

                    if ($failed === []) {
                        Notification::make()
                            ->title('Capture batches dispatched')
                            ->body(count($succeeded) . ' panel(s) queued — ' . implode(', ', $succeeded))
                            ->success()
                            ->send();

                        return;
                    }

                    Notification::make()
                        ->title($succeeded === [] ? 'Dispatch failed' : 'Some dispatches failed')
                        ->body(
                            ($succeeded === [] ? '' : 'Dispatched: ' . implode(', ', $succeeded) . ". \n")
                            . 'Failed: ' . implode('; ', $failed)
                        )
                        ->danger()
                        ->send();
                }),
        ];
    }

    /**
     * @return array<string, string>  panel key => display label
     */
    protected function panelOptionsForRegenerate(): array
    {
        if (! class_exists(\Visualbuilder\FilamentScreenshotCatalogue\PanelRegistry::class)) {
            return collect($this->panelKeys())
                ->mapWithKeys(fn (string $k): array => [$k => \Illuminate\Support\Str::headline($k)])
                ->all();
        }

        $options = [];
        foreach (\Visualbuilder\FilamentScreenshotCatalogue\PanelRegistry::all() as $key => $descriptor) {
            $options[$key] = method_exists($descriptor, 'label')
                ? $descriptor->label()
                : \Illuminate\Support\Str::headline($key);
        }

        return $options;
    }

    /**
     * @return array<string, string>  panel key => heroicon name
     */
    protected function panelIconsForRegenerate(): array
    {
        return collect(array_keys($this->panelOptionsForRegenerate()))
            ->mapWithKeys(fn (string $k): array => [$k => 'heroicon-o-camera'])
            ->all();
    }

    /**
     * Pending-job count on the queue captures land on. Tries the
     * Redis-backed default for safety; if Redis isn't available or the
     * driver is something else, returns 0 silently rather than throwing.
     */
    protected function screenshotQueueLength(): int
    {
        $queue = (string) config('screenshot-catalogue.queue', 'screenshots');

        try {
            return (int) \Illuminate\Support\Facades\Redis::llen('queues:' . $queue);
        } catch (\Throwable $e) {
            return 0;
        }
    }

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

    /**
     * Count of pending captures **as the table actually renders them** —
     * one row per (page, tag) using the same MAX(id) subquery that the
     * resource's table query applies. Without this filter the badge
     * counts every superseded capture too, which won't match what the
     * reviewer sees once they open the tab (e.g. badge says 298, table
     * shows 192 latest-per-page rows).
     */
    protected function pendingCountFor(string $panelKey): ?int
    {
        $latestIds = ScreenshotCapture::query()
            ->selectRaw('MAX(id)')
            ->groupBy('screenshot_page_id', 'tag');

        $count = ScreenshotCapture::query()
            ->whereIn('id', $latestIds)
            ->where('status', ScreenshotStatus::PENDING)
            ->whereHas(
                'screenshotPage',
                fn (Builder $q) => $q->where('panel', $panelKey),
            )
            ->count();

        return $count > 0 ? $count : null;
    }
}
