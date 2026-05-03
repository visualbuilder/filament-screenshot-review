<?php

declare(strict_types=1);

namespace Visualbuilder\FilamentScreenshotReview\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Visualbuilder\FilamentScreenshotReview\Models\ScreenshotPage;

/**
 * Re-captures one screenshot page (panel × slug × viewport × mode) and
 * pulls the new capture into the screenshot_captures table. Dispatched
 * by the recapture HTTP callback that the dev-agent fires once a fix
 * has landed on the dev environment.
 *
 * Runs on the queue because the catalogue's capture command is
 * Playwright-driven and takes ~30s per shot — too long for a synchronous
 * HTTP response. The dev-agent fires-and-forgets the callback; this
 * job does the actual work.
 */
class RecaptureScreenshotJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 2;

    public int $timeout = 180;

    public function __construct(
        public int $pageId,
        public string $tag = 'latest',
    ) {}

    public function handle(): void
    {
        $page = ScreenshotPage::find($this->pageId);
        if ($page === null) {
            return;
        }

        Artisan::call('screenshot:capture', [
            '--panel' => $page->panel,
            '--page' => [$page->slug],
            '--viewport' => [$page->viewport],
            '--mode' => [$page->mode],
            '--tag' => $this->tag,
        ]);

        Artisan::call('screenshot-review:sync-captures', [
            '--panel' => $page->panel,
            '--tag' => $this->tag,
        ]);
    }

    /**
     * @return array<int, string>
     */
    public function tags(): array
    {
        return [
            'screenshot-review',
            'recapture',
            "page:{$this->pageId}",
            "tag:{$this->tag}",
        ];
    }
}
