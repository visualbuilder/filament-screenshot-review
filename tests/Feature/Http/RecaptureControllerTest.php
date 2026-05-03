<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Visualbuilder\FilamentScreenshotReview\Enums\ScreenshotStatus;
use Visualbuilder\FilamentScreenshotReview\Jobs\RecaptureScreenshotJob;
use Visualbuilder\FilamentScreenshotReview\Models\ScreenshotCapture;
use Visualbuilder\FilamentScreenshotReview\Models\ScreenshotPage;
use Visualbuilder\FilamentScreenshotReview\Support\RecaptureUrl;

uses(RefreshDatabase::class);

it('dispatches a recapture job when called with a valid signed URL', function (): void {
    Bus::fake();

    $page = ScreenshotPage::factory()->create();
    $capture = ScreenshotCapture::factory()->for($page, 'screenshotPage')->create([
        'tag' => 'latest',
    ]);

    $url = RecaptureUrl::for($capture);

    $response = $this->get($url);

    $response->assertOk()
        ->assertJsonPath('queued', true)
        ->assertJsonPath('capture_id', $capture->id)
        ->assertJsonPath('page_id', $page->id);

    Bus::assertDispatched(
        RecaptureScreenshotJob::class,
        fn (RecaptureScreenshotJob $job) =>
            $job->pageId === $page->id && $job->tag === 'latest'
    );
});

it('rejects requests with a missing or invalid signature', function (): void {
    $page = ScreenshotPage::factory()->create();
    $capture = ScreenshotCapture::factory()->for($page, 'screenshotPage')->create();

    // Hit the route without a signature query param at all.
    $response = $this->get("/screenshot-review/recapture/{$capture->id}");

    $response->assertForbidden();
});

it('marks a changes_requested capture as approved when the callback fires', function (): void {
    Bus::fake();

    $page = ScreenshotPage::factory()->create();
    $capture = ScreenshotCapture::factory()
        ->for($page, 'screenshotPage')
        ->changesRequested()
        ->create();

    $this->get(RecaptureUrl::for($capture))->assertOk();

    $fresh = $capture->fresh();
    expect($fresh->status)->toBe(ScreenshotStatus::APPROVED)
        ->and($fresh->reviewed_at)->not->toBeNull()
        ->and($fresh->comment)->toContain('Auto-approved via YouTrack callback');
});

it('leaves an already-approved capture untouched', function (): void {
    Bus::fake();

    $page = ScreenshotPage::factory()->create();
    $capture = ScreenshotCapture::factory()
        ->for($page, 'screenshotPage')
        ->approved()
        ->create(['comment' => null]);

    $this->get(RecaptureUrl::for($capture))->assertOk();

    expect($capture->fresh()->comment)->toBeNull();
});
