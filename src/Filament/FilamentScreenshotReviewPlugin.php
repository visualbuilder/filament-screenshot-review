<?php

declare(strict_types=1);

namespace Visualbuilder\FilamentScreenshotReview\Filament;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Visualbuilder\FilamentScreenshotReview\Filament\Pages\ScreenshotReviewStatusPage;
use Visualbuilder\FilamentScreenshotReview\Filament\Resources\ScreenshotCaptures\ScreenshotCaptureResource;
use Visualbuilder\FilamentScreenshotReview\Filament\Resources\ScreenshotPages\ScreenshotPageResource;

class FilamentScreenshotReviewPlugin implements Plugin
{
    public static function make(): static
    {
        return app(static::class);
    }

    public static function get(): static
    {
        return filament(app(static::class)->getId());
    }

    public function getId(): string
    {
        return 'filament-screenshot-review';
    }

    public function register(Panel $panel): void
    {
        $panel
            ->pages([
                ScreenshotReviewStatusPage::class,
            ])
            ->resources([
                ScreenshotPageResource::class,
                ScreenshotCaptureResource::class,
            ]);
    }

    public function boot(Panel $panel): void
    {
        //
    }
}
