<?php

declare(strict_types=1);

namespace Visualbuilder\FilamentScreenshotReview\Filament\Resources\ScreenshotCaptures\Pages;

use Filament\Resources\Pages\ListRecords;
use Visualbuilder\FilamentScreenshotReview\Filament\Resources\ScreenshotCaptures\ScreenshotCaptureResource;

class ListScreenshotCaptures extends ListRecords
{
    protected static string $resource = ScreenshotCaptureResource::class;
}
