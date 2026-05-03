<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Visualbuilder\FilamentScreenshotReview\Enums\ScreenshotStatus;
use Visualbuilder\FilamentScreenshotReview\Filament\Pages\ScreenshotReviewStatusPage;
use Visualbuilder\FilamentScreenshotReview\Models\ScreenshotCapture;
use Visualbuilder\FilamentScreenshotReview\Models\ScreenshotPage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->actingAsTestUser();
});

it('builds one card per registered panel', function (): void {
    ScreenshotPage::factory()->create(['panel' => 'enduser']);
    ScreenshotPage::factory()->create(['panel' => 'admin']);

    $cards = (new ScreenshotReviewStatusPage)->getPanelCards();

    $keys = collect($cards)->pluck('key')->all();
    expect($keys)->toContain('enduser', 'admin');
});

it('reflects capture status counts in the card stats', function (): void {
    $endUserPage = ScreenshotPage::factory()->create(['panel' => 'enduser']);

    ScreenshotCapture::factory()
        ->for($endUserPage, 'screenshotPage')
        ->approved()
        ->create(['captured_at' => now()]);

    $cards = (new ScreenshotReviewStatusPage)->getPanelCards();

    $endUser = collect($cards)->firstWhere('key', 'enduser');

    expect($endUser['approved'])->toBe(1)
        ->and($endUser['pending'])->toBe(0)
        ->and($endUser['changes_requested'])->toBe(0)
        ->and($endUser['last_captured_at'])->not->toBeNull();
});

it('builds a review_url that includes the panel and pending status filters', function (): void {
    ScreenshotPage::factory()->create(['panel' => 'enduser']);

    $cards = (new ScreenshotReviewStatusPage)->getPanelCards();
    $endUser = collect($cards)->firstWhere('key', 'enduser');

    expect($endUser['review_url'])
        ->toContain('panel')
        ->toContain('enduser')
        ->toContain(ScreenshotStatus::PENDING->value);
});
