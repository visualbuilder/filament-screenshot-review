<?php

declare(strict_types=1);

namespace Visualbuilder\FilamentScreenshotReview\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Visualbuilder\FilamentScreenshotReview\Database\Factories\ScreenshotPageFactory;

/**
 * @property int $id
 * @property string $panel
 * @property string $slug
 * @property string $viewport
 * @property string $mode
 * @property string $url
 * @property string|null $label
 * @property int $sort
 * @property bool $included
 * @property \Illuminate\Support\Carbon|null $removed_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, ScreenshotCapture> $captures
 * @property-read ScreenshotCapture|null $latestCapture
 */
class ScreenshotPage extends Model
{
    use HasFactory;

    protected $table = 'screenshot_pages';

    protected $fillable = [
        'panel',
        'slug',
        'viewport',
        'mode',
        'url',
        'label',
        'type',
        'auth',
        'sort',
        'included',
        'removed_at',
    ];

    protected function casts(): array
    {
        return [
            'included' => 'boolean',
            'sort' => 'integer',
            'removed_at' => 'datetime',
        ];
    }

    public function captures(): HasMany
    {
        return $this->hasMany(ScreenshotCapture::class);
    }

    public function latestCapture(): HasOne
    {
        return $this->hasOne(ScreenshotCapture::class)->ofMany('captured_at', 'max');
    }

    protected static function newFactory(): ScreenshotPageFactory
    {
        return ScreenshotPageFactory::new();
    }
}
