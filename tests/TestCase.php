<?php

declare(strict_types=1);

namespace Visualbuilder\FilamentScreenshotReview\Tests;

use Filament\Actions\ActionsServiceProvider;
use Filament\FilamentServiceProvider;
use Filament\Forms\FormsServiceProvider;
use Filament\Infolists\InfolistsServiceProvider;
use Filament\Notifications\NotificationsServiceProvider;
use Filament\Schemas\SchemasServiceProvider;
use Filament\Support\SupportServiceProvider;
use Filament\Tables\TablesServiceProvider;
use Filament\Widgets\WidgetsServiceProvider;
use Illuminate\Database\Eloquent\Factories\Factory;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use Visualbuilder\FilamentScreenshotReview\FilamentScreenshotReviewServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'Visualbuilder\\FilamentScreenshotReview\\Database\\Factories\\'
                . class_basename($modelName) . 'Factory',
        );

        if (class_exists(\Visualbuilder\FilamentPanelScreenshotCatalogue\PanelRegistry::class)) {
            \Visualbuilder\FilamentPanelScreenshotCatalogue\PanelRegistry::flush();
        }

        // Livewire's SupportValidation feature reads view()->shared('errors')
        // when rendering forms/tables. In headless test runs the session-bag
        // share never fires, so seed an empty bag here to keep render paths
        // happy.
        view()->share('errors', new \Illuminate\Support\ViewErrorBag);
    }

    protected function getPackageProviders($app): array
    {
        return [
            ActionsServiceProvider::class,
            FilamentServiceProvider::class,
            FormsServiceProvider::class,
            InfolistsServiceProvider::class,
            LivewireServiceProvider::class,
            NotificationsServiceProvider::class,
            SchemasServiceProvider::class,
            SupportServiceProvider::class,
            TablesServiceProvider::class,
            WidgetsServiceProvider::class,
            FilamentScreenshotReviewServiceProvider::class,
            \Visualbuilder\FilamentScreenshotReview\Tests\Fixtures\TestPanelProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $app['config']->set('app.key', 'base64:Y0ZsZW5IcHRPV0VrM3FFOVJtQ3I0aFpnK0RxQVdwbWY=');

        $app['config']->set('filesystems.default', 'local');

        // Bind a stub s3_public disk so Storage::disk(...)->url(...) resolves
        // without hitting AWS. Capture factories default to s3_public.
        $app['config']->set('filesystems.disks.s3_public', [
            'driver' => 'local',
            'root' => sys_get_temp_dir().'/qa-s3-public',
            'url' => 'http://test.localhost/s3_public',
            'visibility' => 'public',
        ]);

        $app['config']->set('session.driver', 'array');

        $app['config']->set('view.compiled', sys_get_temp_dir().'/qa-views-'.uniqid());

        // Bind a User model so Filament's auth resolution doesn't blow up.
        $app['config']->set('auth.providers.users.model', \Visualbuilder\FilamentScreenshotReview\Tests\Fixtures\TestUser::class);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        \Illuminate\Support\Facades\Schema::create('users', function (\Illuminate\Database\Schema\Blueprint $table): void {
            $table->id();
            $table->string('name')->default('Test User');
            $table->string('email')->unique();
            $table->string('password')->default('secret');
            $table->timestamps();
        });
    }

    protected function actingAsTestUser(): \Visualbuilder\FilamentScreenshotReview\Tests\Fixtures\TestUser
    {
        $user = \Visualbuilder\FilamentScreenshotReview\Tests\Fixtures\TestUser::create([
            'email' => 'qa-'.uniqid().'@test.dev',
        ]);

        $this->actingAs($user);

        return $user;
    }
}
