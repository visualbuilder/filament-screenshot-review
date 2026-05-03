<?php

declare(strict_types=1);

namespace Visualbuilder\FilamentScreenshotReview\Sinks;

use Illuminate\Support\Facades\Log;
use Visualbuilder\FilamentScreenshotReview\Contracts\TicketSink;
use Visualbuilder\FilamentScreenshotReview\Models\ScreenshotCapture;

class NullSink implements TicketSink
{
    public function fileChangeRequest(
        string $summary,
        string $body,
        string $imageUrl,
        ScreenshotCapture $capture,
    ): string {
        Log::info('filament-screenshot-review: NullSink would have filed a ticket', [
            'summary' => $summary,
            'capture_id' => $capture->getKey(),
            'image_url' => $imageUrl,
        ]);

        return '';
    }
}
