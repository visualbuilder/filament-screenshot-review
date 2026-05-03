<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Visualbuilder\FilamentScreenshotReview\Models\ScreenshotCapture;
use Visualbuilder\FilamentScreenshotReview\Models\ScreenshotPage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['filesystems.disks.fake_s3' => ['driver' => 'local', 'root' => sys_get_temp_dir().'/fake-s3-'.uniqid()]]);
    Storage::fake('fake_s3');

    // Override the disk the catalogue's ScreenshotConfig::disk() returns to
    // the fake we just set up. The command falls back to filesystems.default
    // when the catalogue config isn't published, but here we just re-route
    // the catalogue's disk config key.
    config(['panel-screenshot-catalogue.disk' => 'fake_s3']);
});

function placeFakeCapture(string $key, string $contents = 'fake-png-bytes'): void
{
    Storage::disk('fake_s3')->put($key, $contents);
}

it('creates ScreenshotCapture rows from S3 listings', function (): void {
    ScreenshotPage::factory()->create([
        'panel' => 'enduser',
        'slug' => 'orders.index',
        'viewport' => 'desktop',
        'mode' => 'light',
    ]);

    placeFakeCapture('screenshots/testing/enduser/latest/orders.index/desktop-light.png');

    $this->artisan('screenshot-review:sync-captures', [
        '--panel' => 'enduser',
        '--tag' => 'latest',
    ])->assertExitCode(0);

    expect(ScreenshotCapture::count())->toBe(1)
        ->and(ScreenshotCapture::first()->tag)->toBe('latest');
});

it('is idempotent — re-running with identical blobs adds nothing', function (): void {
    ScreenshotPage::factory()->create([
        'panel' => 'enduser',
        'slug' => 'orders.index',
        'viewport' => 'desktop',
        'mode' => 'light',
    ]);

    placeFakeCapture('screenshots/testing/enduser/latest/orders.index/desktop-light.png');

    $this->artisan('screenshot-review:sync-captures', ['--panel' => 'enduser', '--tag' => 'latest'])->run();
    $this->artisan('screenshot-review:sync-captures', ['--panel' => 'enduser', '--tag' => 'latest'])->run();

    expect(ScreenshotCapture::count())->toBe(1);
});

it('inserts a new row when the etag changes for an existing page+tag', function (): void {
    ScreenshotPage::factory()->create([
        'panel' => 'enduser',
        'slug' => 'orders.index',
        'viewport' => 'desktop',
        'mode' => 'light',
    ]);

    placeFakeCapture('screenshots/testing/enduser/latest/orders.index/desktop-light.png', 'first-bytes');
    $this->artisan('screenshot-review:sync-captures', ['--panel' => 'enduser', '--tag' => 'latest'])->run();

    placeFakeCapture('screenshots/testing/enduser/latest/orders.index/desktop-light.png', 'changed-bytes');
    $this->artisan('screenshot-review:sync-captures', ['--panel' => 'enduser', '--tag' => 'latest'])->run();

    expect(ScreenshotCapture::count())->toBe(2);
});

it('skips captures whose page row is missing', function (): void {
    placeFakeCapture('screenshots/testing/enduser/latest/orphan/desktop-light.png');

    $this->artisan('screenshot-review:sync-captures', ['--panel' => 'enduser', '--tag' => 'latest'])->run();

    expect(ScreenshotCapture::count())->toBe(0);
});
