<?php

declare(strict_types=1);

namespace Visualbuilder\FilamentScreenshotReview\Filament\Resources\ScreenshotPages\Pages;

use Filament\Resources\Pages\ListRecords;
use Visualbuilder\FilamentScreenshotReview\Filament\Resources\ScreenshotPages\ScreenshotPageResource;

class ListScreenshotPages extends ListRecords
{
    protected static string $resource = ScreenshotPageResource::class;
}
