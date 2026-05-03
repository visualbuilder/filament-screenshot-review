<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Visualbuilder\FilamentScreenshotReview\Filament\Resources\ScreenshotPages\ScreenshotPageResource;
use Visualbuilder\FilamentScreenshotReview\Models\ScreenshotPage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->actingAsTestUser();
});

it('registers the page resource on the qa-tests panel', function (): void {
    expect(Filament::getPanel('qa-tests')->getResources())
        ->toContain(ScreenshotPageResource::class);
});

it('add_url header action creates a page with included=true', function (): void {
    $reflection = new ReflectionClass(ScreenshotPageResource::class);
    $method = $reflection->getMethod('addUrlAction');
    $method->setAccessible(true);
    $action = $method->invoke(null);

    $action->call(['data' => [
        'panel' => 'enduser',
        'slug' => 'custom.report',
        'label' => 'Custom Report',
        'url' => '/custom-report',
        'viewport' => 'desktop',
        'mode' => 'light',
    ]]);

    $page = ScreenshotPage::where('slug', 'custom.report')->first();
    expect($page)->not->toBeNull()
        ->and($page->included)->toBeTrue()
        ->and($page->sort)->toBe(9999);
});

it('toggle on the included column updates the database via observer', function (): void {
    config(['screenshot-review.sitemap_json_path' => sys_get_temp_dir().'/sitemap-{panel}.json']);
    $path = sys_get_temp_dir().'/sitemap-enduser.json';
    @unlink($path);

    $page = ScreenshotPage::factory()->create(['panel' => 'enduser', 'slug' => 'orders.index']);

    expect(is_file($path))->toBeTrue();

    $page->update(['included' => false]);

    $contents = json_decode((string) file_get_contents($path), true);
    expect($contents)->toBe([]);
});
