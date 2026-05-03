<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Panel
    |--------------------------------------------------------------------------
    |
    | Filament panel ID where the plugin's resources/pages should mount when
    | the package's service provider auto-registers the plugin. Hosts that
    | mount the plugin manually (the recommended pattern) can ignore this.
    |
    */
    'panel' => env('FILAMENT_SCREENSHOT_QA_PANEL', 'design-system'),

    /*
    |--------------------------------------------------------------------------
    | Permission gate strings
    |--------------------------------------------------------------------------
    |
    | Each value is either a permission string (resolved via the host's
    | permission system, e.g. spatie/laravel-permission) or null. When null,
    | the policy method returns true for any authenticated user.
    |
    */
    'permissions' => [
        'pages.viewAny' => null,
        'pages.manage' => 'screenshotQa.pages.manage',
        'captures.viewAny' => null,
        'captures.review' => 'screenshotQa.captures.review',
    ],

    /*
    |--------------------------------------------------------------------------
    | Sitemap JSON path
    |--------------------------------------------------------------------------
    |
    | Where the catalogue writes its panel sitemap. This package mirrors the
    | JSON into editable DB rows and regenerates the JSON when reviewers
    | toggle inclusion. `{panel}` is replaced with the Filament panel ID.
    |
    */
    'sitemap_json_path' => storage_path('app/sitemap-{panel}.json'),

    /*
    |--------------------------------------------------------------------------
    | Viewports & modes to materialise per page
    |--------------------------------------------------------------------------
    |
    | Each sitemap entry expands into one ScreenshotPage row per
    | (viewport × mode) combination, so reviewers can include/exclude
    | each variant independently.
    |
    */
    'viewports' => ['desktop', 'tablet', 'mobile'],
    'modes' => ['light', 'dark'],
];
