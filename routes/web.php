<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Visualbuilder\FilamentScreenshotReview\Http\Controllers\RecaptureController;

Route::middleware(['web', 'signed'])
    ->prefix('screenshot-review')
    ->group(function (): void {
        Route::match(['get', 'post'], 'recapture/{capture}', RecaptureController::class)
            ->name('screenshot-review.recapture');
    });
