@php
    $record = $getRecord();
    $page = $record->screenshotPage;

    $viewportIcon = match ($page->viewport) {
        'desktop' => 'heroicon-o-computer-desktop',
        'tablet' => 'heroicon-o-device-tablet',
        'mobile' => 'heroicon-o-device-phone-mobile',
        default => 'heroicon-o-question-mark-circle',
    };
    $modeIcon = match ($page->mode) {
        'light' => 'heroicon-o-sun',
        'dark' => 'heroicon-o-moon',
        default => 'heroicon-o-question-mark-circle',
    };
@endphp

<div style="display: flex; align-items: center; justify-content: center; gap: 0.5rem; flex-wrap: wrap;">
    <span style="display: inline-flex; align-items: center; gap: 0.35rem; padding: 0.25rem 0.625rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 500; line-height: 1; background: rgba(148,163,184,0.18); color: rgb(203,213,225);">
        <x-filament::icon :icon="$viewportIcon" style="width: 0.875rem; height: 0.875rem;" />
        {{ ucfirst($page->viewport) }}
    </span>
    <span style="display: inline-flex; align-items: center; gap: 0.35rem; padding: 0.25rem 0.625rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 500; line-height: 1; background: rgba(148,163,184,0.18); color: rgb(203,213,225);">
        <x-filament::icon :icon="$modeIcon" style="width: 0.875rem; height: 0.875rem;" />
        {{ ucfirst($page->mode) }}
    </span>
</div>
