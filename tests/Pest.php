<?php

declare(strict_types=1);

use Visualbuilder\FilamentScreenshotReview\Tests\TestCase;

uses(TestCase::class)->in('Unit', 'Feature');

/**
 * Reach into a Filament resource and invoke one of the protected static
 * action factories — keeps tests focused on the action's behaviour without
 * having to render the full Livewire/Filament UI.
 */
function invokeProtected(string $methodName, string $resource = \Visualbuilder\FilamentScreenshotReview\Filament\Resources\ScreenshotCaptures\ScreenshotCaptureResource::class): mixed
{
    $reflection = new \ReflectionClass($resource);
    $method = $reflection->getMethod($methodName);
    $method->setAccessible(true);

    return $method->invoke(null);
}
