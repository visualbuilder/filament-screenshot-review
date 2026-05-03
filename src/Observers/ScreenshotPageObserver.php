<?php

declare(strict_types=1);

namespace Visualbuilder\FilamentScreenshotReview\Observers;

use Visualbuilder\FilamentScreenshotReview\Models\ScreenshotPage;
use Visualbuilder\FilamentScreenshotReview\Services\SitemapWriter;

class ScreenshotPageObserver
{
    public function __construct(private SitemapWriter $writer) {}

    public function saved(ScreenshotPage $page): void
    {
        if (static::$suppressed) {
            return;
        }

        $this->writer->writeForPanel($page->panel);
    }

    public function deleted(ScreenshotPage $page): void
    {
        if (static::$suppressed) {
            return;
        }

        $this->writer->writeForPanel($page->panel);
    }

    /**
     * Sitemap writes are noisy when sync-pages is bulk-upserting hundreds of
     * rows in one pass. The command brackets its work with suppression, then
     * fires a single write at the end.
     */
    protected static bool $suppressed = false;

    public static function withoutSitemapWrites(callable $callback): mixed
    {
        $previous = static::$suppressed;
        static::$suppressed = true;

        try {
            return $callback();
        } finally {
            static::$suppressed = $previous;
        }
    }
}
