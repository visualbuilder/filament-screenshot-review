<?php

declare(strict_types=1);

namespace Visualbuilder\FilamentScreenshotReview\Contracts;

use Visualbuilder\FilamentScreenshotReview\Models\ScreenshotCapture;

interface TicketSink
{
    /**
     * File a "changes requested" ticket against the host's issue tracker.
     *
     * @return string Public URL of the created ticket. Return an empty string
     *                if the sink doesn't actually file tickets (e.g. NullSink).
     */
    public function fileChangeRequest(
        string $summary,
        string $body,
        string $imageUrl,
        ScreenshotCapture $capture,
    ): string;
}
