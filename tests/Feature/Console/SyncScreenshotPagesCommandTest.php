<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Visualbuilder\FilamentScreenshotReview\Models\ScreenshotPage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->jsonPath = sys_get_temp_dir().'/sitemap-enduser.json';
    config(['screenshot-review.sitemap_json_path' => sys_get_temp_dir().'/sitemap-{panel}.json']);
    @unlink($this->jsonPath);
});

afterEach(function (): void {
    @unlink($this->jsonPath);
});

function writeSitemap(string $path, array $entries): void
{
    file_put_contents($path, json_encode($entries));
}

it('upserts ScreenshotPage rows from sitemap json', function (): void {
    writeSitemap($this->jsonPath, [
        ['slug' => 'orders.index', 'url' => '/orders', 'label' => 'Orders', 'sort' => 100],
        ['slug' => 'profile', 'url' => '/profile', 'label' => 'Profile', 'sort' => 200],
    ]);

    $this->artisan('screenshot-review:sync-pages', ['--panel' => 'enduser'])
        ->assertExitCode(0);

    // 2 entries × 3 viewports × 2 modes = 12 rows
    expect(ScreenshotPage::count())->toBe(12)
        ->and(ScreenshotPage::where('slug', 'orders.index')->count())->toBe(6)
        ->and(ScreenshotPage::where('slug', 'profile')->count())->toBe(6);
});

it('preserves the included flag on existing rows during a re-sync', function (): void {
    writeSitemap($this->jsonPath, [
        ['slug' => 'orders.index', 'url' => '/orders', 'label' => 'Orders', 'sort' => 100],
    ]);

    $this->artisan('screenshot-review:sync-pages', ['--panel' => 'enduser']);

    // Reviewer excludes one variant.
    $page = ScreenshotPage::where('viewport', 'mobile')->where('mode', 'dark')->first();
    $page->update(['included' => false]);

    // Re-sync — included flag must NOT revert.
    $this->artisan('screenshot-review:sync-pages', ['--panel' => 'enduser']);

    expect($page->fresh()->included)->toBeFalse();
});

it('sets removed_at when a page falls out of the sitemap', function (): void {
    writeSitemap($this->jsonPath, [
        ['slug' => 'orders.index', 'url' => '/orders', 'sort' => 100],
        ['slug' => 'profile', 'url' => '/profile', 'sort' => 200],
    ]);

    $this->artisan('screenshot-review:sync-pages', ['--panel' => 'enduser']);

    writeSitemap($this->jsonPath, [
        ['slug' => 'orders.index', 'url' => '/orders', 'sort' => 100],
    ]);

    $this->artisan('screenshot-review:sync-pages', ['--panel' => 'enduser']);

    $profileRows = ScreenshotPage::where('slug', 'profile')->get();
    $orderRows = ScreenshotPage::where('slug', 'orders.index')->get();

    expect($profileRows)->each(
        fn ($row) => $row->removed_at->not->toBeNull(),
    );

    expect($orderRows)->each(
        fn ($row) => $row->removed_at->toBeNull(),
    );
});

it('clears removed_at when a page reappears in the sitemap', function (): void {
    writeSitemap($this->jsonPath, [
        ['slug' => 'orders.index', 'url' => '/orders', 'sort' => 100],
    ]);
    $this->artisan('screenshot-review:sync-pages', ['--panel' => 'enduser']);

    writeSitemap($this->jsonPath, []);
    $this->artisan('screenshot-review:sync-pages', ['--panel' => 'enduser']);

    expect(ScreenshotPage::whereNotNull('removed_at')->count())->toBe(6);

    writeSitemap($this->jsonPath, [
        ['slug' => 'orders.index', 'url' => '/orders', 'sort' => 100],
    ]);
    $this->artisan('screenshot-review:sync-pages', ['--panel' => 'enduser']);

    expect(ScreenshotPage::whereNotNull('removed_at')->count())->toBe(0);
});
