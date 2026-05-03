<?php

declare(strict_types=1);

namespace Visualbuilder\FilamentScreenshotReview\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Visualbuilder\FilamentScreenshotReview\Enums\ScreenshotStatus;
use Visualbuilder\FilamentScreenshotReview\Models\ScreenshotCapture;
use Visualbuilder\FilamentScreenshotReview\Models\ScreenshotPage;

/**
 * @extends Factory<ScreenshotCapture>
 */
class ScreenshotCaptureFactory extends Factory
{
    protected $model = ScreenshotCapture::class;

    public function definition(): array
    {
        return [
            'screenshot_page_id' => ScreenshotPage::factory(),
            'tag' => 'latest',
            's3_disk' => 's3_public',
            's3_key' => 'screenshots/'.$this->faker->uuid().'.png',
            'etag' => $this->faker->md5(),
            'size' => $this->faker->numberBetween(50_000, 500_000),
            'captured_at' => now(),
            'status' => ScreenshotStatus::PENDING,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn () => [
            'status' => ScreenshotStatus::APPROVED,
            'reviewed_at' => now(),
        ]);
    }

    public function changesRequested(): static
    {
        return $this->state(fn () => [
            'status' => ScreenshotStatus::CHANGES_REQUESTED,
            'comment' => 'Spacing on the header is too tight, please widen.',
            'reviewed_at' => now(),
        ]);
    }
}
