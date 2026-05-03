<?php

declare(strict_types=1);

namespace Visualbuilder\FilamentScreenshotReview\Filament\Pages;

use Filament\Pages\Page;
use Illuminate\Support\Facades\Storage;
use Visualbuilder\FilamentScreenshotReview\Enums\ScreenshotStatus;
use Visualbuilder\FilamentScreenshotReview\Filament\Resources\ScreenshotCaptures\ScreenshotCaptureResource;
use Visualbuilder\FilamentScreenshotReview\Models\ScreenshotCapture;
use Visualbuilder\FilamentScreenshotReview\Models\ScreenshotPage;

class ScreenshotReviewStatusPage extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-presentation-chart-bar';

    protected static ?string $navigationLabel = 'Status';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = '';

    protected string $view = 'filament-screenshot-review::pages.screenshot-review-status';

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getPanelCards(): array
    {
        return collect($this->panelKeys())
            ->map(fn (string $key) => $this->buildCard($key))
            ->all();
    }

    /**
     * @return array<int, string>
     */
    protected function panelKeys(): array
    {
        if (class_exists(\Visualbuilder\FilamentPanelScreenshotCatalogue\PanelRegistry::class)) {
            $registered = \Visualbuilder\FilamentPanelScreenshotCatalogue\PanelRegistry::keys();

            if ($registered !== []) {
                return $registered;
            }
        }

        // Fall back to whatever panels actually have rows, so the page still
        // renders something useful when no descriptors are registered (tests,
        // host that mounts the plugin standalone).
        return ScreenshotPage::query()
            ->distinct()
            ->pluck('panel')
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildCard(string $key): array
    {
        $approved = ScreenshotPage::query()
            ->where('panel', $key)
            ->where('included', true)
            ->whereHas('latestCapture',
                fn ($q) => $q->where('status', ScreenshotStatus::APPROVED->value))
            ->count();

        $pending = ScreenshotPage::query()
            ->where('panel', $key)
            ->where('included', true)
            ->whereHas('latestCapture',
                fn ($q) => $q->where('status', ScreenshotStatus::PENDING->value))
            ->count();

        $changesRequested = ScreenshotPage::query()
            ->where('panel', $key)
            ->where('included', true)
            ->whereHas('latestCapture',
                fn ($q) => $q->where('status', ScreenshotStatus::CHANGES_REQUESTED->value))
            ->count();

        $latest = ScreenshotCapture::query()
            ->whereHas('screenshotPage', fn ($q) => $q->where('panel', $key))
            ->latest('captured_at')
            ->first();

        $hero = ScreenshotCapture::query()
            ->whereHas('screenshotPage', fn ($q) => $q
                ->where('panel', $key)
                ->where('viewport', 'desktop')
                ->where('mode', 'light'))
            ->orderBy('captured_at', 'desc')
            ->first();

        return [
            'key' => $key,
            'label' => $this->descriptorLabel($key),
            'hero_url' => $hero ? Storage::disk($hero->s3_disk)->url($hero->s3_key) : null,
            'approved' => $approved,
            'pending' => $pending,
            'changes_requested' => $changesRequested,
            'last_captured_at' => $latest?->captured_at,
            'latest_tag' => $latest?->tag,
            'review_url' => ScreenshotCaptureResource::getUrl('index', [
                'tableFilters' => [
                    'panel' => ['values' => [$key]],
                    'status' => ['values' => [ScreenshotStatus::PENDING->value]],
                ],
            ]),
        ];
    }

    protected function descriptorLabel(string $key): string
    {
        if (class_exists(\Visualbuilder\FilamentPanelScreenshotCatalogue\PanelRegistry::class)) {
            $descriptor = \Visualbuilder\FilamentPanelScreenshotCatalogue\PanelRegistry::get($key);
            if ($descriptor) {
                return ucfirst($descriptor->panelId);
            }
        }

        return ucfirst($key);
    }
}
