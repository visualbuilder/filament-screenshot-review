<?php

declare(strict_types=1);

namespace Visualbuilder\FilamentScreenshotReview;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Visualbuilder\FilamentScreenshotReview\Console\Commands\SyncScreenshotCapturesCommand;
use Visualbuilder\FilamentScreenshotReview\Console\Commands\SyncScreenshotPagesCommand;
use Visualbuilder\FilamentScreenshotReview\Contracts\TicketSink;
use Visualbuilder\FilamentScreenshotReview\Models\ScreenshotPage;
use Visualbuilder\FilamentScreenshotReview\Observers\ScreenshotPageObserver;
use Visualbuilder\FilamentScreenshotReview\Sinks\NullSink;

class FilamentScreenshotReviewServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package->name('filament-screenshot-review')
            ->hasConfigFile('screenshot-review')
            ->hasMigrations([
                '2026_05_03_000001_create_screenshot_pages_table',
                '2026_05_03_000002_create_screenshot_captures_table',
            ])
            ->runsMigrations()
            ->hasViews('filament-screenshot-review')
            ->hasRoute('web')
            ->hasCommands([
                SyncScreenshotPagesCommand::class,
                SyncScreenshotCapturesCommand::class,
            ]);
    }

    public function packageRegistered(): void
    {
        // bindIf so a host that defines its own TicketSink in a separate
        // service provider wins — load order between provider trees is not
        // guaranteed otherwise.
        $this->app->bindIf(TicketSink::class, NullSink::class);
    }

    public function packageBooted(): void
    {
        ScreenshotPage::observe(ScreenshotPageObserver::class);

        // Listen for the catalogue's ScreenshotCaptured event so the
        // review UI fills incrementally as a capture batch progresses,
        // instead of waiting for the batch's `finally` sync to fire at
        // the end. Gated on the catalogue's event class existing — no
        // hard dep, the review package still works standalone with the
        // command-driven sync flow.
        if (class_exists(\Visualbuilder\FilamentScreenshotCatalogue\Events\ScreenshotCaptured::class)) {
            \Illuminate\Support\Facades\Event::listen(
                \Visualbuilder\FilamentScreenshotCatalogue\Events\ScreenshotCaptured::class,
                \Visualbuilder\FilamentScreenshotReview\Listeners\CreateScreenshotCaptureRow::class,
            );
        }

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../.claude/commands' => base_path('.claude/commands'),
            ], 'filament-screenshot-review-claude-skills');
        }
    }
}
