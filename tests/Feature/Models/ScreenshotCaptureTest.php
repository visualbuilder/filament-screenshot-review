<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Visualbuilder\FilamentScreenshotReview\Enums\ScreenshotStatus;
use Visualbuilder\FilamentScreenshotReview\Models\ScreenshotCapture;
use Visualbuilder\FilamentScreenshotReview\Models\ScreenshotPage;

uses(RefreshDatabase::class);

it('creates a valid capture via factory', function (): void {
    $capture = ScreenshotCapture::factory()->create();

    expect($capture->status)->toBe(ScreenshotStatus::PENDING)
        ->and($capture->screenshot_page_id)->not->toBeNull();
});

it('belongs to a screenshot page', function (): void {
    $page = ScreenshotPage::factory()->create();
    $capture = ScreenshotCapture::factory()->for($page, 'screenshotPage')->create();

    expect($capture->screenshotPage->id)->toBe($page->id);
});

it('casts status to the ScreenshotStatus enum', function (): void {
    $capture = ScreenshotCapture::factory()->approved()->create();

    expect($capture->status)->toBe(ScreenshotStatus::APPROVED);
});
