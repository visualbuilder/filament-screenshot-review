@php
    $record = $getRecord();
    // Append the etag (md5 of the uploaded bytes) as a cache-buster
    // query param. The S3 key is canonical per (panel, slug,
    // viewport, mode, tag) — so re-captures overwrite the same key
    // and the browser would otherwise serve stale cached bytes
    // (especially in the lightbox, which often shows the previous
    // batch's image until a hard reload). The query param doesn't
    // affect the S3 fetch but invalidates the browser's image cache
    // any time the content changes.
    $url = \Illuminate\Support\Facades\Storage::disk($record->s3_disk)->url($record->s3_key);
    if (! empty($record->etag)) {
        $url .= (str_contains($url, '?') ? '&' : '?') . 'v=' . substr($record->etag, 0, 12);
    }
    $page = $record->screenshotPage;
    $caption = $page->panel . ' · ' . ($page->label ?? $page->slug)
        . ' (' . $page->viewport . '-' . $page->mode . ')';
@endphp

<div
    x-data="{ open: false }"
    style="display: flex; align-items: center; justify-content: center; width: 100%; min-height: 360px; background: transparent;"
>
    <img
        src="{{ $url }}"
        loading="lazy"
        alt="{{ $caption }}"
        style="display: block; margin: 0 auto; max-width: 100%; max-height: 360px; width: auto; height: auto; object-fit: contain; cursor: zoom-in; border-radius: 0.625rem; box-shadow: 0 1px 2px rgba(0,0,0,0.05), 0 1px 1px rgba(0,0,0,0.04);"
        @click="open = true"
    />

    <template x-teleport="body">
        <div
            x-show="open"
            x-cloak
            x-transition.opacity
            @keydown.escape.window="open = false"
            @click="open = false"
            style="position: fixed !important; top: 0 !important; left: 0 !important; right: 0 !important; bottom: 0 !important; width: 100vw !important; height: 100vh !important; z-index: 9999 !important; display: flex !important; align-items: center !important; justify-content: center !important; background: rgba(0,0,0,0.85); padding: 2rem; cursor: zoom-out; transform: none !important; margin: 0 !important;"
        >
            <img
                src="{{ $url }}"
                alt="{{ $caption }}"
                style="display: block; max-width: 90vw; max-height: 90vh; width: auto; height: auto; object-fit: contain; border: 1px solid rgba(255,255,255,0.35); padding: 0.5rem; background: rgba(255,255,255,0.06); border-radius: 0.5rem; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.6); margin: 0 auto;"
                @click.stop
            />
        </div>
    </template>
</div>
