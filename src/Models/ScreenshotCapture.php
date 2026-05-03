<?php

declare(strict_types=1);

namespace Visualbuilder\FilamentScreenshotReview\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Visualbuilder\FilamentScreenshotReview\Database\Factories\ScreenshotCaptureFactory;
use Visualbuilder\FilamentScreenshotReview\Enums\ScreenshotStatus;

/**
 * @property int $id
 * @property int $screenshot_page_id
 * @property string $tag
 * @property string $s3_disk
 * @property string $s3_key
 * @property string $etag
 * @property int $size
 * @property \Illuminate\Support\Carbon $captured_at
 * @property ScreenshotStatus $status
 * @property string|null $comment
 * @property string|null $reviewed_by_type
 * @property int|null $reviewed_by_id
 * @property \Illuminate\Support\Carbon|null $reviewed_at
 * @property string|null $ticket_url
 * @property-read ScreenshotPage $screenshotPage
 */
class ScreenshotCapture extends Model
{
    use HasFactory;

    protected $table = 'screenshot_captures';

    protected $fillable = [
        'screenshot_page_id',
        'tag',
        's3_disk',
        's3_key',
        'etag',
        'size',
        'captured_at',
        'status',
        'comment',
        'reviewed_by_type',
        'reviewed_by_id',
        'reviewed_at',
        'ticket_url',
    ];

    protected function casts(): array
    {
        return [
            'status' => ScreenshotStatus::class,
            'captured_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'size' => 'integer',
        ];
    }

    public function screenshotPage(): BelongsTo
    {
        return $this->belongsTo(ScreenshotPage::class);
    }

    public function reviewedBy(): MorphTo
    {
        return $this->morphTo();
    }

    protected static function newFactory(): ScreenshotCaptureFactory
    {
        return ScreenshotCaptureFactory::new();
    }
}
