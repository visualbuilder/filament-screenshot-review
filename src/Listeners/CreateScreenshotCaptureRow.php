<?php

declare(strict_types=1);

namespace Visualbuilder\FilamentScreenshotReview\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Carbon;
use Visualbuilder\FilamentScreenshotReview\Models\ScreenshotCapture;
use Visualbuilder\FilamentScreenshotReview\Models\ScreenshotPage;

/**
 * Listens for the catalogue's ScreenshotCaptured event and creates a
 * matching screenshot_captures row immediately, so the review UI fills
 * incrementally as a batch progresses (instead of waiting for the
 * batch's `finally` sync at the end).
 *
 * Idempotent on (page_id, tag, etag): re-firing the event for the same
 * shot is a no-op. If the etag changed (different image content), a
 * new row is inserted — same dedupe semantics as
 * `screenshot-review:sync-captures`.
 */
class CreateScreenshotCaptureRow implements ShouldQueue
{
    /** Listeners go on the same queue as the capture jobs they trail. */
    public function viaQueue(): string
    {
        return (string) config('screenshot-catalogue.queue', 'screenshots');
    }

    public function handle(\Visualbuilder\FilamentScreenshotCatalogue\Events\ScreenshotCaptured $event): void
    {
        $page = ScreenshotPage::query()
            ->where('panel', $event->panelKey)
            ->where('slug', $event->slug)
            ->where('viewport', $event->viewport)
            ->where('mode', $event->mode)
            ->first();

        if ($page === null) {
            // Capture for a slug not yet in the catalogue (sitemap
            // hasn't been synced yet). Skip silently — the batch's
            // tail-end sync-captures pass will pick this row up once
            // sync-pages runs.
            return;
        }

        $existing = ScreenshotCapture::query()
            ->where('screenshot_page_id', $page->id)
            ->where('tag', $event->version)
            ->where('etag', $event->etag)
            ->first();

        if ($existing !== null) {
            return;
        }

        ScreenshotCapture::create([
            'screenshot_page_id' => $page->id,
            'tag' => $event->version,
            's3_disk' => $event->disk,
            's3_key' => $event->key,
            'etag' => $event->etag,
            'size' => $event->size,
            'captured_at' => Carbon::now(),
        ]);
    }
}
