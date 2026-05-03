# Filament Screenshot Review

Human QA review on top of [`visualbuilder/filament-panel-screenshot-catalogue`](https://github.com/visualbuilder/filament-panel-screenshot-catalogue).

Mounts as a Filament v5 plugin and adds three navigation surfaces:

- **Status** — at-a-glance health card per registered panel
- **Captures** — full-width grid of capture cards, inline approve / request-changes per card
- **Pages** — sitemap management; toggle inclusion, add custom URLs

When a reviewer requests changes, the package files a ticket through a host-defined `TicketSink` with the captured image URL embedded in the ticket body.

## Install

```bash
composer require --dev visualbuilder/filament-screenshot-review
php artisan migrate
```

Mount the plugin on whichever panel you choose (default convention: the `design-system` panel):

```php
// app/Providers/Filament/DesignSystemPanelProvider.php
->plugins([
    \Visualbuilder\FilamentScreenshotReview\Filament\FilamentScreenshotReviewPlugin::make(),
])
```

## Ticket sink

Bind your own `TicketSink` implementation to file tickets in your tracker:

```php
// app/Providers/AppServiceProvider.php
$this->app->bind(
    \Visualbuilder\FilamentScreenshotReview\Contracts\TicketSink::class,
    \App\TicketSinks\YoutrackTicketSink::class,
);
```

The default binding is `NullSink`, which logs and returns an empty URL.

## Workflow

1. `php artisan screenshot-review:sync-pages --panel=enduser` — populate the page registry from the catalogue's sitemap JSON.
2. `php artisan screenshot:dispatch --panel=enduser --tag=latest` — capture every included page.
3. `php artisan screenshot-review:sync-captures --panel=enduser --tag=latest` — pull capture metadata into the DB.
4. Open the **Captures** tab in Filament — review each card, approve or request changes.

## License

GPL-2.0-or-later. See `LICENSE.md`.
