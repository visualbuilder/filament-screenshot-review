<?php

declare(strict_types=1);

namespace Visualbuilder\FilamentScreenshotReview\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Visualbuilder\FilamentScreenshotReview\Models\ScreenshotPage;

/**
 * @extends Factory<ScreenshotPage>
 */
class ScreenshotPageFactory extends Factory
{
    protected $model = ScreenshotPage::class;

    public function definition(): array
    {
        $slug = $this->faker->unique()->slug(2);

        return [
            'panel' => 'enduser',
            'slug' => $slug,
            'viewport' => 'desktop',
            'mode' => 'light',
            'url' => '/'.$slug,
            'label' => $this->faker->sentence(2),
            'sort' => 1000,
            'included' => true,
            'removed_at' => null,
        ];
    }

    public function excluded(): static
    {
        return $this->state(fn () => ['included' => false]);
    }

    public function removed(): static
    {
        return $this->state(fn () => ['removed_at' => now()]);
    }
}
