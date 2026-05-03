<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Log;
use Visualbuilder\FilamentScreenshotReview\Models\ScreenshotCapture;
use Visualbuilder\FilamentScreenshotReview\Sinks\NullSink;

it('logs at info level and returns an empty url', function (): void {
    Log::shouldReceive('info')->once()->with(
        'filament-screenshot-review: NullSink would have filed a ticket',
        \Mockery::on(fn ($context) => isset($context['summary'], $context['image_url'])),
    );

    $capture = (new ScreenshotCapture)->forceFill(['id' => 42]);

    $url = (new NullSink)->fileChangeRequest('summary', 'body', 'http://x/a.png', $capture);

    expect($url)->toBe('');
});
