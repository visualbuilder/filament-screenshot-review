<?php

declare(strict_types=1);

namespace Visualbuilder\FilamentScreenshotReview\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Visualbuilder\FilamentScreenshotReview\Models\ScreenshotCapture;
use Visualbuilder\FilamentScreenshotReview\Models\ScreenshotPage;

/**
 * Reads S3 (or any configured Flysystem disk) for capture artefacts and
 * upserts them into the screenshot_captures table. Idempotent on the
 * (page, tag, etag) tuple — re-running with the same blobs is a no-op.
 */
class SyncScreenshotCapturesCommand extends Command
{
    protected $signature = 'screenshot-review:sync-captures
        {--panel= : Panel key to sync, omit to sync every registered panel}
        {--tag=latest : Tag to sync}';

    protected $description = 'Sync capture metadata from S3 into the screenshot_captures table.';

    public function handle(): int
    {
        $tag = (string) $this->option('tag');
        $keys = $this->panelKeys();

        if ($keys === []) {
            $this->error('No panels to sync. Pass --panel=KEY or register descriptors with PanelRegistry::register().');

            return self::FAILURE;
        }

        $totals = ['added' => 0, 'skipped' => 0];

        foreach ($keys as $key) {
            $totals = $this->syncPanel($key, $tag, $totals);
        }

        $this->info(sprintf(
            'Sync complete — added %d capture(s), %d skipped (already present).',
            $totals['added'],
            $totals['skipped'],
        ));

        return self::SUCCESS;
    }

    /**
     * @return array<int, string>
     */
    private function panelKeys(): array
    {
        if ($key = $this->option('panel')) {
            return [$key];
        }

        if (class_exists(\Visualbuilder\FilamentPanelScreenshotCatalogue\PanelRegistry::class)) {
            return \Visualbuilder\FilamentPanelScreenshotCatalogue\PanelRegistry::keys();
        }

        return [];
    }

    /**
     * @param  array{added:int,skipped:int}  $totals
     * @return array{added:int,skipped:int}
     */
    private function syncPanel(string $key, string $tag, array $totals): array
    {
        $panelId = $this->panelInternalId($key);
        $diskName = $this->diskName();
        $disk = Storage::disk($diskName);

        $prefix = $this->prefixFor($panelId, $tag);
        $files = $disk->allFiles($prefix);

        foreach ($files as $path) {
            if (! str_ends_with($path, '.png')) {
                continue;
            }

            $parsed = $this->parseKey($path, $prefix);
            if ($parsed === null) {
                continue;
            }

            $page = ScreenshotPage::query()
                ->where('panel', $key)
                ->where('slug', $parsed['slug'])
                ->where('viewport', $parsed['viewport'])
                ->where('mode', $parsed['mode'])
                ->first();

            if ($page === null) {
                // Capture exists for an excluded/removed page — skip silently.
                continue;
            }

            // S3 returns an MD5-shaped etag for non-multipart uploads, so we
            // hash the file contents to match. For local fakes used in tests
            // this gives us a deterministic value regardless of disk driver.
            $contents = (string) $disk->get($path);
            $etag = md5($contents);

            $existing = ScreenshotCapture::query()
                ->where('screenshot_page_id', $page->id)
                ->where('tag', $tag)
                ->where('etag', $etag)
                ->first();

            if ($existing) {
                $totals['skipped']++;

                continue;
            }

            ScreenshotCapture::create([
                'screenshot_page_id' => $page->id,
                'tag' => $tag,
                's3_disk' => $diskName,
                's3_key' => $path,
                'etag' => $etag,
                'size' => $disk->size($path),
                'captured_at' => Carbon::createFromTimestamp($disk->lastModified($path)),
            ]);

            $totals['added']++;
        }

        return $totals;
    }

    private function panelInternalId(string $key): string
    {
        if (class_exists(\Visualbuilder\FilamentPanelScreenshotCatalogue\Services\ScreenshotConfig::class)) {
            return \Visualbuilder\FilamentPanelScreenshotCatalogue\Services\ScreenshotConfig::panelInternalId($key);
        }

        return $key;
    }

    private function diskName(): string
    {
        if (class_exists(\Visualbuilder\FilamentPanelScreenshotCatalogue\Services\ScreenshotConfig::class)) {
            return \Visualbuilder\FilamentPanelScreenshotCatalogue\Services\ScreenshotConfig::disk();
        }

        return (string) config('filesystems.default', 'local');
    }

    private function prefixFor(string $panelId, string $tag): string
    {
        if (class_exists(\Visualbuilder\FilamentPanelScreenshotCatalogue\Services\ScreenshotConfig::class)) {
            $env = \Visualbuilder\FilamentPanelScreenshotCatalogue\Services\ScreenshotConfig::resolveEnv();

            return rtrim(
                \Visualbuilder\FilamentPanelScreenshotCatalogue\Services\ScreenshotConfig::s3KeyPrefix($env, $panelId, $tag),
                '/',
            );
        }

        return "screenshots/{$panelId}/{$tag}";
    }

    /**
     * Catalogue layout: {prefix}/{slug}/{viewport}-{mode}.png
     *
     * @return array{slug:string,viewport:string,mode:string}|null
     */
    private function parseKey(string $path, string $prefix): ?array
    {
        $relative = ltrim(substr($path, strlen($prefix)), '/');
        $segments = explode('/', $relative);
        if (count($segments) < 2) {
            return null;
        }

        $filename = array_pop($segments);
        $slug = implode('/', $segments);

        if (! preg_match('/^(desktop|tablet|mobile)-(light|dark)\.png$/', $filename, $m)) {
            return null;
        }

        return [
            'slug' => $slug,
            'viewport' => $m[1],
            'mode' => $m[2],
        ];
    }
}
