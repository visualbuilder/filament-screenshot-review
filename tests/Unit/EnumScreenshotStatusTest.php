<?php

declare(strict_types=1);

use Visualbuilder\FilamentScreenshotReview\Enums\ScreenshotStatus;

it('exposes the three review states', function (): void {
    expect(ScreenshotStatus::cases())
        ->toHaveCount(3)
        ->and(ScreenshotStatus::PENDING->value)->toBe('pending')
        ->and(ScreenshotStatus::APPROVED->value)->toBe('approved')
        ->and(ScreenshotStatus::CHANGES_REQUESTED->value)->toBe('changes_requested');
});

it('returns Filament-friendly metadata for each state', function (): void {
    expect(ScreenshotStatus::PENDING->getLabel())->toBe('Pending')
        ->and(ScreenshotStatus::PENDING->getColor())->toBe('gray')
        ->and(ScreenshotStatus::PENDING->getIcon())->toBe('heroicon-o-clock');

    expect(ScreenshotStatus::APPROVED->getLabel())->toBe('Approved')
        ->and(ScreenshotStatus::APPROVED->getColor())->toBe('success')
        ->and(ScreenshotStatus::APPROVED->getIcon())->toBe('heroicon-o-check-circle');

    expect(ScreenshotStatus::CHANGES_REQUESTED->getLabel())->toBe('Changes requested')
        ->and(ScreenshotStatus::CHANGES_REQUESTED->getColor())->toBe('warning')
        ->and(ScreenshotStatus::CHANGES_REQUESTED->getIcon())->toBe('heroicon-o-exclamation-triangle');
});
