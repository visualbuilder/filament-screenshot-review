<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Visualbuilder\FilamentScreenshotReview\Models\ScreenshotCapture;
use Visualbuilder\FilamentScreenshotReview\Models\ScreenshotPage;

uses(RefreshDatabase::class);

it('creates a valid page via factory', function (): void {
    $page = ScreenshotPage::factory()->create();

    expect($page->panel)->toBe('enduser')
        ->and($page->included)->toBeTrue()
        ->and($page->removed_at)->toBeNull();
});

it('returns the most recent capture as latestCapture', function (): void {
    $page = ScreenshotPage::factory()->create();

    $older = ScreenshotCapture::factory()->for($page, 'screenshotPage')->create([
        'captured_at' => now()->subDay(),
    ]);
    $newer = ScreenshotCapture::factory()->for($page, 'screenshotPage')->create([
        'captured_at' => now(),
    ]);

    $page->refresh();

    expect($page->latestCapture->id)->toBe($newer->id)
        ->and($page->captures)->toHaveCount(2);
});

it('regenerates the sitemap json on save via the observer', function (): void {
    config(['screenshot-review.sitemap_json_path' => sys_get_temp_dir().'/sitemap-{panel}.json']);

    $path = sys_get_temp_dir().'/sitemap-enduser.json';
    @unlink($path);

    ScreenshotPage::factory()->create([
        'panel' => 'enduser',
        'slug' => 'orders.index',
        'label' => 'Orders',
    ]);

    expect(is_file($path))->toBeTrue();

    $contents = json_decode((string) file_get_contents($path), true);
    expect($contents)->toBeArray()
        ->and($contents)->toHaveCount(1)
        ->and($contents[0]['slug'])->toBe('orders.index')
        ->and($contents[0]['label'])->toBe('Orders');
});

it('excludes rows that are not included from the regenerated sitemap', function (): void {
    config(['screenshot-review.sitemap_json_path' => sys_get_temp_dir().'/sitemap-{panel}.json']);

    ScreenshotPage::factory()->create(['panel' => 'enduser', 'slug' => 'kept', 'included' => true]);
    ScreenshotPage::factory()->create(['panel' => 'enduser', 'slug' => 'dropped', 'included' => false]);

    $contents = json_decode((string) file_get_contents(sys_get_temp_dir().'/sitemap-enduser.json'), true);

    expect(collect($contents)->pluck('slug')->all())->toBe(['kept']);
});
