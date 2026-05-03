@php
    $record = $getRecord();
    $url = \Illuminate\Support\Facades\Storage::disk($record->s3_disk)->url($record->s3_key);
    $page = $record->screenshotPage;
    $caption = $page->panel . ' · ' . ($page->label ?? $page->slug)
        . ' (' . $page->viewport . '-' . $page->mode . ')';
@endphp

<div
    x-data="{ open: false }"
    style="text-align: center; background: transparent;"
>
    <img
        src="{{ $url }}"
        loading="lazy"
        alt="{{ $caption }}"
        style="max-width: 100%; max-height: 360px; object-fit: contain; cursor: zoom-in; border: 1px solid rgba(148,163,184,0.25); padding: 0.5rem; background: rgba(148,163,184,0.06); border-radius: 0.5rem;"
        @click="open = true"
    />

    <template x-teleport="body">
        <div
            x-show="open"
            x-cloak
            x-transition.opacity
            @keydown.escape.window="open = false"
            @click="open = false"
            style="position: fixed; inset: 0; z-index: 9999; display: flex; align-items: center; justify-content: center; background: rgba(0,0,0,0.85); padding: 2rem; cursor: zoom-out;"
        >
            <img
                src="{{ $url }}"
                alt="{{ $caption }}"
                style="max-width: 90%; max-height: 90vh; object-fit: contain; border: 1px solid rgba(255,255,255,0.35); padding: 0.5rem; background: rgba(255,255,255,0.06); border-radius: 0.5rem; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.6);"
                @click.stop
            />
        </div>
    </template>
</div>
