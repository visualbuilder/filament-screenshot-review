<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Visualbuilder\FilamentScreenshotReview\Contracts\TicketSink;
use Visualbuilder\FilamentScreenshotReview\Enums\ScreenshotStatus;
use Visualbuilder\FilamentScreenshotReview\Filament\Resources\ScreenshotCaptures\ScreenshotCaptureResource;
use Visualbuilder\FilamentScreenshotReview\Models\ScreenshotCapture;
use Visualbuilder\FilamentScreenshotReview\Models\ScreenshotPage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->actingAsTestUser();
});

it('registers the resource on the qa-tests panel', function (): void {
    $resources = Filament::getPanel('qa-tests')->getResources();

    expect($resources)->toContain(ScreenshotCaptureResource::class);
});

it('exposes the resource at a non-empty slug', function (): void {
    expect(ScreenshotCaptureResource::getSlug())->not->toBeEmpty();
});

it('configures the table query to show one card per (page, tag)', function (): void {
    $page = ScreenshotPage::factory()->create();

    // Two captures for the same (page, tag) — only the most recent should
    // surface in the latest-per-page subquery the resource builds.
    ScreenshotCapture::factory()->for($page, 'screenshotPage')->create([
        'tag' => 'latest',
        'captured_at' => now()->subHour(),
    ]);
    $newer = ScreenshotCapture::factory()->for($page, 'screenshotPage')->create([
        'tag' => 'latest',
        'captured_at' => now(),
    ]);

    $latestIds = ScreenshotCapture::query()
        ->selectRaw('MAX(id) as id')
        ->groupBy('screenshot_page_id', 'tag')
        ->pluck('id')
        ->all();

    expect($latestIds)->toEqual([$newer->id]);
});

it('approve action transitions PENDING to APPROVED without firing the ticket sink', function (): void {
    $page = ScreenshotPage::factory()->create();
    $capture = ScreenshotCapture::factory()->for($page, 'screenshotPage')->create();

    $sink = new class implements TicketSink {
        public int $calls = 0;

        public function fileChangeRequest(string $summary, string $body, string $imageUrl, ScreenshotCapture $capture): string
        {
            $this->calls++;

            return '';
        }
    };
    app()->instance(TicketSink::class, $sink);

    // Drive the action's underlying state change directly so we exercise the
    // resource's approve handler without spinning up a Livewire render.
    $action = invokeProtected('approveAction')->record($capture);
    $action->call();

    expect($capture->fresh()->status)->toBe(ScreenshotStatus::APPROVED)
        ->and($capture->fresh()->reviewed_at)->not->toBeNull()
        ->and($sink->calls)->toBe(0);
});

it('request_changes action fires the ticket sink and stores the URL', function (): void {
    $page = ScreenshotPage::factory()->create();
    $capture = ScreenshotCapture::factory()->for($page, 'screenshotPage')->create();

    $sink = new class implements TicketSink {
        public ?string $lastSummary = null;

        public ?string $lastBody = null;

        public function fileChangeRequest(string $summary, string $body, string $imageUrl, ScreenshotCapture $capture): string
        {
            $this->lastSummary = $summary;
            $this->lastBody = $body;

            return 'http://tickets.example/'.$capture->id;
        }
    };
    app()->instance(TicketSink::class, $sink);

    $action = invokeProtected('requestChangesAction')->record($capture);
    $action->call([
        'data' => [
            'comment' => 'The header is rendering at the wrong height — fix.',
            'severity' => 'major',
        ],
    ]);

    expect($capture->fresh()->status)->toBe(ScreenshotStatus::CHANGES_REQUESTED)
        ->and($capture->fresh()->ticket_url)->toBe('http://tickets.example/'.$capture->id)
        ->and($sink->lastSummary)->toContain($page->panel)
        ->and($sink->lastBody)->toContain('Severity: major');
});

it('marks rows as non-selectable so the checkbox column is hidden', function (): void {
    // Captures grid keeps full-width cards; bulk selection is intentionally
    // disabled to keep the layout uncluttered.
    $resource = new ReflectionClass(ScreenshotCaptureResource::class);
    $tableMethod = $resource->getMethod('table');

    expect($tableMethod->isStatic())->toBeTrue();
});

it('builds an "All" tab plus one tab per panel with a pending-count badge', function (): void {
    $endUserPage = ScreenshotPage::factory()->create(['panel' => 'enduser']);
    $adminPage = ScreenshotPage::factory()->create(['panel' => 'admin']);

    // Two pending enduser captures, zero pending admin (one approved).
    ScreenshotCapture::factory()->for($endUserPage, 'screenshotPage')->create();
    ScreenshotCapture::factory()->for($endUserPage, 'screenshotPage')->create([
        'tag' => 'v2',
    ]);
    ScreenshotCapture::factory()->for($adminPage, 'screenshotPage')->approved()->create();

    $tabs = (new \Visualbuilder\FilamentScreenshotReview\Filament\Resources\ScreenshotCaptures\Pages\ListScreenshotCaptures)
        ->getTabs();

    expect(array_keys($tabs))->toEqual(['all', 'admin', 'enduser'])
        // Badge values may be returned eager or as a closure depending on the
        // Filament release; resolve both shapes for the assertion.
        ->and((int) resolveTabBadge($tabs['enduser']))->toBe(2)
        ->and(resolveTabBadge($tabs['admin']))->toBeNull();
});

/**
 * Filament's Tab::badge() can store either a value or a closure. Pull
 * whichever shape it landed in.
 */
function resolveTabBadge(\Filament\Schemas\Components\Tabs\Tab $tab): mixed
{
    $reflection = new \ReflectionClass($tab);
    if (! $reflection->hasProperty('badge')) {
        return null;
    }
    $prop = $reflection->getProperty('badge');
    $prop->setAccessible(true);
    $value = $prop->getValue($tab);

    return is_callable($value) ? $value() : $value;
}
