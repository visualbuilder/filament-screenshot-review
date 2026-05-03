<?php

declare(strict_types=1);

namespace Visualbuilder\FilamentScreenshotReview\Filament\Resources\ScreenshotPages\Pages;

use Filament\Resources\Pages\EditRecord;
use Visualbuilder\FilamentScreenshotReview\Filament\Resources\ScreenshotPages\ScreenshotPageResource;

class EditScreenshotPage extends EditRecord
{
    protected static string $resource = ScreenshotPageResource::class;
}
