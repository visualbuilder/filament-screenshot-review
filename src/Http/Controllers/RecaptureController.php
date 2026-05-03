<?php

declare(strict_types=1);

namespace Visualbuilder\FilamentScreenshotReview\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Visualbuilder\FilamentScreenshotReview\Enums\ScreenshotStatus;
use Visualbuilder\FilamentScreenshotReview\Jobs\RecaptureScreenshotJob;
use Visualbuilder\FilamentScreenshotReview\Models\ScreenshotCapture;

/**
 * HTTP entry point for the "QA approved the YouTrack ticket" callback.
 * The route is signed (Laravel APP_KEY), so the dev-agent box doesn't
 * need any shared secret with the QA box.
 *
 * Behaviour: the QAer approves the ticket in YouTrack (where the
 * dev-agent posts its own self-verification screenshots); on transition
 * to the configured "done" state, this URL is fired. We:
 *
 *   1. Mark the original `changes_requested` capture as `approved` so
 *      the dashboard's stats reflect the resolution.
 *   2. Queue a re-capture so the latest image of the (now-fixed) page
 *      is on file. The new capture lands as `pending` — a final visual
 *      sanity check on the dashboard before it's locked in.
 */
class RecaptureController
{
    public function __invoke(Request $request, ScreenshotCapture $capture): JsonResponse
    {
        $page = $capture->screenshotPage;
        if ($page === null) {
            return response()->json(['error' => 'page not found'], 404);
        }

        if ($capture->status === ScreenshotStatus::CHANGES_REQUESTED) {
            $capture->forceFill([
                'status' => ScreenshotStatus::APPROVED,
                'reviewed_at' => Carbon::now(),
                'comment' => trim(($capture->comment ?? '') . "\n\n— Auto-approved via YouTrack callback."),
            ])->save();
        }

        RecaptureScreenshotJob::dispatch(
            pageId: $page->getKey(),
            tag: $capture->tag,
        );

        return response()->json([
            'queued' => true,
            'resolved' => true,
            'capture_id' => $capture->getKey(),
            'page_id' => $page->getKey(),
            'panel' => $page->panel,
            'slug' => $page->slug,
        ]);
    }
}
