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

    protected static string|\UnitEnum|null $navigationGroup = 'Screenshot Review';

    protected static ?int $navigationSort = 1;

    // Lives at the panel root (`/design-system/`) so it acts as the
    // landing page when reviewers click the panel name. Changing this
    // to a non-empty slug breaks Filament's panel-home redirect on
    // hosts that don't register an alternative root page.
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
        if (class_exists(\Visualbuilder\FilamentScreenshotCatalogue\PanelRegistry::class)) {
            $registered = \Visualbuilder\FilamentScreenshotCatalogue\PanelRegistry::keys();

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
            'catalogue_url' => $this->catalogueUrl($key),
        ];
    }

    /**
     * Public S3 URL of the per-panel `latest/index.html` catalogue page
     * — the browsable grid the catalogue produces alongside each
     * dispatch. Returns null when the catalogue isn't loaded
     * standalone), or when the panel has no captures yet (so we don't
     * link to a 404).
     */
    protected function catalogueUrl(string $key): ?string
    {
        if (! class_exists(\Visualbuilder\FilamentScreenshotCatalogue\Services\ScreenshotConfig::class)) {
            return null;
        }

        $env = \Visualbuilder\FilamentScreenshotCatalogue\Services\ScreenshotConfig::resolveEnv();
        $panelId = \Visualbuilder\FilamentScreenshotCatalogue\Services\ScreenshotConfig::panelInternalId($key);
        $disk = \Visualbuilder\FilamentScreenshotCatalogue\Services\ScreenshotConfig::disk();

        $indexKey = \Visualbuilder\FilamentScreenshotCatalogue\Services\ScreenshotConfig::s3Key(
            $env, $panelId, 'latest', '', 'index.html',
        );

        try {
            if (! Storage::disk($disk)->exists($indexKey)) {
                return null;
            }

            return Storage::disk($disk)->url($indexKey);
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected function descriptorLabel(string $key): string
    {
        if (class_exists(\Visualbuilder\FilamentScreenshotCatalogue\PanelRegistry::class)) {
            $descriptor = \Visualbuilder\FilamentScreenshotCatalogue\PanelRegistry::get($key);
            if ($descriptor) {
                return ucfirst($descriptor->panelId);
            }
        }

        return ucfirst($key);
    }
}
