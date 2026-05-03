<?php

declare(strict_types=1);

namespace Visualbuilder\FilamentScreenshotReview\Filament\Resources\ScreenshotCaptures\Pages;

use Filament\Resources\Pages\ViewRecord;
use Visualbuilder\FilamentScreenshotReview\Filament\Resources\ScreenshotCaptures\ScreenshotCaptureResource;

class ViewScreenshotCapture extends ViewRecord
{
    protected static string $resource = ScreenshotCaptureResource::class;
}
