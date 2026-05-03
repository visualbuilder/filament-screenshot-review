@php
    /** @var \Visualbuilder\FilamentScreenshotReview\Models\ScreenshotCapture $record */
    $page = $record->screenshotPage;
    $url = \Illuminate\Support\Facades\Storage::disk($record->s3_disk)->url($record->s3_key);
    $title = ($page->label ?? $page->slug);
    $viewportMode = $page->viewport . '-' . $page->mode;
    $status = $record->status;
    $statusColorMap = [
        'gray' => 'fi-color-gray bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300',
        'success' => 'fi-color-success bg-success-100 text-success-700 dark:bg-success-500/20 dark:text-success-400',
        'warning' => 'fi-color-warning bg-warning-100 text-warning-700 dark:bg-warning-500/20 dark:text-warning-400',
    ];
    $statusClasses = $statusColorMap[$status->getColor()] ?? $statusColorMap['gray'];
@endphp

<div x-data="{ zoom: false }" class="flex flex-col gap-2">
    <button
        type="button"
        @click="zoom = true"
        style="
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            height: 360px;
            overflow: hidden;
            border-radius: 0.5rem;
            background: #f9fafb;
            cursor: zoom-in;
            border: 1px solid rgba(0,0,0,0.08);
            padding: 0;
        "
    >
        <img
            src="{{ $url }}"
            alt="{{ $title }}"
            loading="lazy"
            style="
                max-width: 100%;
                max-height: 100%;
                width: auto;
                height: auto;
                display: block;
                margin: auto;
            "
        />
    </button>

    <div class="flex flex-col gap-1.5">
        <p class="text-sm font-semibold text-gray-950 dark:text-white">
            <span class="text-gray-500 dark:text-gray-400 font-normal">{{ $page->panel }}</span>
            <span class="px-1 text-gray-400">·</span>
            {{ $title }}
        </p>

        <div class="flex flex-wrap items-center gap-2">
            <span class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300">
                {{ $viewportMode }}
            </span>
            <span class="inline-flex items-center gap-1 rounded-md px-2 py-0.5 text-xs font-medium {{ $statusClasses }}">
                <x-filament::icon :icon="$status->getIcon()" class="h-3.5 w-3.5" />
                {{ $status->getLabel() }}
            </span>
        </div>
    </div>

    {{-- Lightbox: click image to zoom; click backdrop or press ESC to close. --}}
    <template x-teleport="body">
        <div
            x-show="zoom"
            x-cloak
            x-transition.opacity
            @click="zoom = false"
            @keydown.escape.window="zoom = false"
            class="fixed inset-0 z-[100] flex items-center justify-center bg-black/85 p-6"
            style="cursor: zoom-out;"
        >
            <img
                src="{{ $url }}"
                alt="{{ $title }}"
                class="max-w-full max-h-full object-contain rounded-md shadow-2xl"
                @click.stop
            />
            <button
                type="button"
                @click="zoom = false"
                class="absolute top-4 right-4 inline-flex items-center justify-center rounded-full bg-white/10 hover:bg-white/20 text-white p-2"
                aria-label="Close"
            >
                <x-filament::icon icon="heroicon-o-x-mark" class="h-5 w-5" />
            </button>
        </div>
    </template>
</div>
