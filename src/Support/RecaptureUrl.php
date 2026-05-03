<?php

declare(strict_types=1);

namespace Visualbuilder\FilamentScreenshotReview\Support;

use Illuminate\Support\Facades\URL;
use Visualbuilder\FilamentScreenshotReview\Models\ScreenshotCapture;

/**
 * Builds the signed callback URL the host embeds into a YouTrack issue
 * when "Request changes" fires. The dev-agent (running on a separate box)
 * hits this URL once the fix has landed on the dev environment — the
 * receiving controller then dispatches a single-page re-capture and a
 * follow-up sync, surfacing a fresh `pending` capture for the reviewer.
 *
 * Auth model: Laravel signed URLs. The signature is computed with the
 * host's APP_KEY at file-time and verified on inbound. No shared secret
 * needs to leave the QA box.
 */
class RecaptureUrl
{
    /**
     * Build a signed URL valid for $ttlDays days. Default 30 — long enough
     * to cover a slow dev-agent cycle, short enough that stale URLs in
     * old tickets don't keep working forever.
     */
    public static function for(ScreenshotCapture $capture, int $ttlDays = 30): string
    {
        return URL::temporarySignedRoute(
            'screenshot-review.recapture',
            now()->addDays($ttlDays),
            ['capture' => $capture->getKey()],
        );
    }
}
