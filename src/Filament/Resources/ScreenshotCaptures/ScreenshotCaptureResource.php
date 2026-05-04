<?php

declare(strict_types=1);

namespace Visualbuilder\FilamentScreenshotReview\Filament\Resources\ScreenshotCaptures;

use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Visualbuilder\FilamentScreenshotReview\Contracts\TicketSink;
use Visualbuilder\FilamentScreenshotReview\Enums\ScreenshotStatus;
use Visualbuilder\FilamentScreenshotReview\Filament\Resources\ScreenshotCaptures\Pages\ListScreenshotCaptures;
use Visualbuilder\FilamentScreenshotReview\Filament\Resources\ScreenshotCaptures\Pages\ViewScreenshotCapture;
use Visualbuilder\FilamentScreenshotReview\Jobs\RecaptureScreenshotJob;
use Visualbuilder\FilamentScreenshotReview\Models\ScreenshotCapture;

class ScreenshotCaptureResource extends Resource
{
    protected static ?string $model = ScreenshotCapture::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-camera';

    protected static ?string $navigationLabel = 'Captures';

    protected static string|\UnitEnum|null $navigationGroup = 'Screenshot Review';

    protected static ?int $navigationSort = 2;

    public static function getMaxContentWidth(): Width|string|null
    {
        return Width::Full;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query
                ->with(['screenshotPage', 'reviewedBy'])
                ->whereIn('id', static::latestPerPageSubquery())
            )
            ->defaultSort('captured_at', 'desc')
            // contentGrid only applies when columns are wrapped in a Stack /
            // Split layout — without one the table renders 1 row per record
            // regardless of the breakpoints below.
            ->contentGrid([
                'default' => 1,
                'md' => 2,
                'lg' => 3,
                '2xl' => 4,
            ])
            // Per-page options chosen so every page fills the grid evenly at
            // every breakpoint — 12, 24, 48 are all multiples of 1, 2, 3, 4
            // (the column counts at default / md / lg / 2xl). No half-empty
            // rows on the last page regardless of viewport width.
            ->paginated([12, 24, 48, 'all'])
            ->defaultPaginationPageOption(24)
            // Card layout — image cell owns its own Blade so we can host the
            // Alpine click-to-zoom lightbox without dragging custom HTML
            // through the rest of the card. Title / badges / status are
            // native TextColumns so dark mode + the enum's HasColor/HasIcon
            // contribute styling for free.
            ->columns([
                Stack::make([
                    // Card header — page name (clickable to live URL),
                    // then a row of two badges (viewport + mode) so the
                    // device variant is identifiable at a glance.
                    // Apple-style: generous vertical rhythm between the
                    // header / badges / image / actions so each block
                    // reads as a discrete layer rather than a stack of
                    // crammed rows.
                    TextColumn::make('title')
                        ->state(fn (ScreenshotCapture $r): string =>
                            $r->screenshotPage->label ?? $r->screenshotPage->slug)
                        ->weight('bold')
                        ->alignCenter()
                        // Click the title to open the actual page in a new
                        // tab — handy when a reviewer wants to verify the
                        // live UI matches the capture before approving.
                        ->url(fn (ScreenshotCapture $r): ?string => static::pageUrl($r))
                        ->openUrlInNewTab()
                        // Activates the toolbar search box. Searches across
                        // the page's label, slug, and url so a reviewer can
                        // jump to a capture by any of the strings they
                        // remember.
                        ->searchable(query: function ($query, string $search) {
                            $term = '%' . $search . '%';

                            return $query->whereHas('screenshotPage', function ($q) use ($term) {
                                $q->where('label', 'like', $term)
                                    ->orWhere('slug', 'like', $term)
                                    ->orWhere('url', 'like', $term);
                            });
                        }),
                    // Two side-by-side badges — viewport (info / warning /
                    // success per device class) and mode (sun / moon icon).
                    // Status badge intentionally omitted from the card body
                    // since the toolbar tabs + the Approve / Request
                    // changes buttons already convey state.
                    // Two device badges centred together as a pair —
                    // Filament's Split layout column would push them to
                    // opposite edges (flex space-between), so we render
                    // them via a tiny view that controls its own gap and
                    // alignment.
                    ViewColumn::make('meta')
                        ->view('filament-screenshot-review::columns.meta-badges'),
                    // Image last — the buttons sit underneath via the
                    // record-action row Filament renders for free.
                    ViewColumn::make('image')
                        ->view('filament-screenshot-review::columns.image-with-lightbox'),
                    // Tiny secondary timestamp under the image — relative
                    // time (e.g. "2 minutes ago") with the absolute date
                    // tooltipped on hover. Small, muted; helps a reviewer
                    // know how fresh the capture is without crowding the
                    // header.
                    TextColumn::make('captured_at')
                        ->state(fn (ScreenshotCapture $r): string =>
                            $r->captured_at?->diffForHumans() ?? '')
                        ->tooltip(fn (ScreenshotCapture $r): ?string =>
                            $r->captured_at?->toDayDateTimeString())
                        ->size('xs')
                        ->color('gray')
                        ->alignCenter(),
                ])
                    ->space(3) // larger vertical gap between header, badges, image
                    ->alignment(\Filament\Support\Enums\Alignment::Center)
                    ->extraAttributes(['style' => 'padding: 1rem;']),
            ])
            // Hide the column manager trigger — the cards are a single
            // Stack column, so reordering / hiding has nothing useful to
            // do, and the trigger just adds noise to the toolbar.
            ->columnManager(false)
            ->filters([
                // Panel filter dropped — the page tabs do this job, and
                // having both in the toolbar muddied which one was active.
                // Combined viewport + mode filter — both toggles stack
                // vertically inside the same grid cell. Viewport-and-mode
                // is the closest pairing of capture dimensions, so they
                // read naturally as a "device variant" picker. Frees up a
                // grid column at the lg breakpoint and gives each toggle
                // group its own row so neither overflows the cell width.
                Filter::make('device')
                    ->schema([
                        ToggleButtons::make('viewports')
                            ->label('Viewports')
                            ->options([
                                'desktop' => 'Desktop',
                                'tablet' => 'Tablet',
                                'mobile' => 'Mobile',
                            ])
                            ->icons([
                                'desktop' => 'heroicon-o-computer-desktop',
                                'tablet' => 'heroicon-o-device-tablet',
                                'mobile' => 'heroicon-o-device-phone-mobile',
                            ])
                            ->multiple()
                            ->inline(),
                        ToggleButtons::make('modes')
                            ->label('Modes')
                            ->options([
                                'light' => 'Light',
                                'dark' => 'Dark',
                            ])
                            ->icons([
                                'light' => 'heroicon-o-sun',
                                'dark' => 'heroicon-o-moon',
                            ])
                            ->multiple()
                            ->inline(),
                    ])
                    ->query(function ($query, array $data) {
                        $viewports = $data['viewports'] ?? [];
                        $modes = $data['modes'] ?? [];

                        return $query
                            ->when(! empty($viewports), fn ($q) => $q->whereHas(
                                'screenshotPage',
                                fn ($p) => $p->whereIn('viewport', $viewports),
                            ))
                            ->when(! empty($modes), fn ($q) => $q->whereHas(
                                'screenshotPage',
                                fn ($p) => $p->whereIn('mode', $modes),
                            ));
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if (! empty($data['viewports'] ?? [])) {
                            $indicators['viewports'] = 'Viewports: ' . implode(', ', $data['viewports']);
                        }
                        if (! empty($data['modes'] ?? [])) {
                            $indicators['modes'] = 'Modes: ' . implode(', ', $data['modes']);
                        }

                        return $indicators;
                    }),
                // Combined review filter: Status toggles + Tag select
                // stacked vertically in the same grid cell. Pairs the two
                // filters that drive "what does this reviewer need to look
                // at right now" — status (pending vs approved) + tag
                // (which capture run) are usually adjusted together. Two
                // grid columns total: Device on the left, Review on the
                // right.
                Filter::make('review')
                    ->schema([
                        ToggleButtons::make('statuses')
                            ->label('Statuses')
                            ->options([
                                ScreenshotStatus::PENDING->value => 'Pending',
                                ScreenshotStatus::APPROVED->value => 'Approved',
                                ScreenshotStatus::CHANGES_REQUESTED->value => 'Changes',
                            ])
                            // All three buttons share the primary colour to
                            // match the viewport / mode toggle styling — the
                            // status-specific colours stay in play on the
                            // capture-card badges, where they actually
                            // signal state at a glance.
                            ->colors([
                                ScreenshotStatus::PENDING->value => 'primary',
                                ScreenshotStatus::APPROVED->value => 'primary',
                                ScreenshotStatus::CHANGES_REQUESTED->value => 'primary',
                            ])
                            ->icons([
                                ScreenshotStatus::PENDING->value => 'heroicon-o-clock',
                                ScreenshotStatus::APPROVED->value => 'heroicon-o-check-circle',
                                ScreenshotStatus::CHANGES_REQUESTED->value => 'heroicon-o-exclamation-triangle',
                            ])
                            ->multiple()
                            ->inline()
                            ->default([ScreenshotStatus::PENDING->value]),
                        Select::make('tag')
                            ->label('Tag')
                            ->options(fn () => ScreenshotCapture::query()
                                ->distinct()
                                ->pluck('tag', 'tag')
                                ->all())
                            ->native(false)
                            ->searchable()
                            ->default('latest'),
                    ])
                    ->query(function ($query, array $data) {
                        $statuses = $data['statuses'] ?? [];
                        $tag = $data['tag'] ?? null;

                        return $query
                            ->when(! empty($statuses), fn ($q) => $q->whereIn('status', $statuses))
                            ->when(filled($tag), fn ($q) => $q->where('tag', $tag));
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];

                        $statuses = $data['statuses'] ?? [];
                        if (! empty($statuses)) {
                            $labels = collect($statuses)
                                ->map(fn ($v) => ScreenshotStatus::from($v)->getLabel())
                                ->implode(', ');
                            $indicators['statuses'] = "Status: {$labels}";
                        }

                        $tag = $data['tag'] ?? null;
                        if (filled($tag)) {
                            $indicators['tag'] = "Tag: {$tag}";
                        }

                        return $indicators;
                    }),
            ], layout: FiltersLayout::AboveContent)
            // Two grid columns at lg: [Device] [Review]. Each cell hosts
            // two stacked schema components — viewports+modes on the left,
            // statuses+tag on the right. Stacks to one column on phones.
            ->filtersFormColumns([
                'default' => 1,
                'lg' => 2,
            ])
            ->recordActions([
                static::approveAction(),
                static::requestChangesAction(),
                static::recaptureAction(),
                static::viewTicketAction(),
            ])
            ->checkIfRecordIsSelectableUsing(fn (): bool => false)
            ->toolbarActions([]);
    }

    /**
     * Subquery: latest capture id per (page, tag) pair so the grid renders
     * one card per page rather than one card per capture event.
     */
    protected static function latestPerPageSubquery()
    {
        return ScreenshotCapture::query()
            ->selectRaw('MAX(id)')
            ->groupBy('screenshot_page_id', 'tag');
    }

    /**
     * Resolve the absolute URL of the captured page so the title can link
     * straight to the live UI. Builds it from the catalogue's
     * PanelDescriptor (panel host + scheme) and the page's stored path.
     * Returns null when the catalogue isn't loaded or the panel isn't
     * registered (in which case the title falls back to plain text).
     */
    protected static function pageUrl(ScreenshotCapture $r): ?string
    {
        if (! class_exists(\Visualbuilder\FilamentScreenshotCatalogue\PanelRegistry::class)) {
            return null;
        }

        $page = $r->screenshotPage;
        $descriptor = \Visualbuilder\FilamentScreenshotCatalogue\PanelRegistry::get($page->panel);
        if ($descriptor === null) {
            return null;
        }

        $domain = rtrim((string) $descriptor->domain, '/');
        if ($domain === '') {
            return null;
        }

        $path = '/' . ltrim((string) $page->url, '/');

        return 'https://' . $domain . $path;
    }


    protected static function approveAction(): Action
    {
        return Action::make('approve')
            ->label('Approve')
            ->color('success')
            ->icon('heroicon-o-check')
            ->outlined()  // outlined button — Apple-style soft tinted action, less shouty than solid fill
            ->visible(fn (ScreenshotCapture $r): bool =>
                $r->status === ScreenshotStatus::PENDING)
            ->authorize(fn (ScreenshotCapture $r): bool =>
                auth()->user()?->can('approve', $r) ?? true)
            ->action(function (ScreenshotCapture $record): void {
                $user = auth()->user();
                $record->forceFill([
                    'status' => ScreenshotStatus::APPROVED,
                    'reviewed_by_type' => $user ? $user::class : null,
                    'reviewed_by_id' => $user?->getAuthIdentifier(),
                    'reviewed_at' => Carbon::now(),
                ])->save();

                Notification::make()
                    ->title('Approved')
                    ->success()
                    ->send();
            });
    }

    protected static function requestChangesAction(): Action
    {
        return Action::make('request_changes')
            ->label('Request changes')
            ->color('warning')
            ->icon('heroicon-o-exclamation-triangle')
            ->outlined()  // outlined button — Apple-style soft tinted action, less shouty than solid fill
            ->visible(fn (ScreenshotCapture $r): bool =>
                $r->status === ScreenshotStatus::PENDING)
            ->authorize(fn (ScreenshotCapture $r): bool =>
                auth()->user()?->can('requestChanges', $r) ?? true)
            ->schema([
                Textarea::make('comment')
                    ->required()
                    ->minLength(20)
                    ->rows(5)
                    ->placeholder('What needs to change? Be specific — this is what the dev-agent reads to act on.'),
                // Toggle-button group beats the dropdown — one tap to change
                // severity instead of "click select, click option". Pre-set
                // to "major" so the most common case is a zero-click default.
                ToggleButtons::make('severity')
                    ->options([
                        'blocker' => 'Blocker',
                        'major' => 'Major',
                        'minor' => 'Minor',
                    ])
                    ->colors([
                        'blocker' => 'danger',
                        'major' => 'warning',
                        'minor' => 'gray',
                    ])
                    ->icons([
                        'blocker' => 'heroicon-o-exclamation-circle',
                        'major' => 'heroicon-o-exclamation-triangle',
                        'minor' => 'heroicon-o-information-circle',
                    ])
                    ->grouped()
                    ->required()
                    ->default('major'),
            ])
            ->action(function (ScreenshotCapture $record, array $data): void {
                $user = auth()->user();
                $record->forceFill([
                    'status' => ScreenshotStatus::CHANGES_REQUESTED,
                    'comment' => $data['comment'],
                    'reviewed_by_type' => $user ? $user::class : null,
                    'reviewed_by_id' => $user?->getAuthIdentifier(),
                    'reviewed_at' => Carbon::now(),
                ])->save();

                $imageUrl = Storage::disk($record->s3_disk)->url($record->s3_key);
                $page = $record->screenshotPage;
                $summary = sprintf(
                    '[VR] %s/%s (%s-%s)',
                    $page->panel,
                    $page->slug,
                    $page->viewport,
                    $page->mode,
                );
                $body = $data['comment'] . "\n\nSeverity: " . $data['severity'];

                $sink = app(TicketSink::class);
                $url = $sink->fileChangeRequest($summary, $body, $imageUrl, $record);

                if ($url !== '') {
                    $record->forceFill(['ticket_url' => $url])->save();
                }

                Notification::make()
                    ->title($url !== '' ? 'Changes requested — ticket filed' : 'Changes requested')
                    ->warning()
                    ->body($url !== '' ? "Ticket: {$url}" : 'No ticket sink configured.')
                    ->send();
            });
    }

    protected static function viewTicketAction(): Action
    {
        return Action::make('view_ticket')
            ->label('View ticket')
            ->color('primary')
            ->icon('heroicon-o-arrow-top-right-on-square')
            ->button()
            ->visible(fn (ScreenshotCapture $r): bool => filled($r->ticket_url))
            ->url(fn (ScreenshotCapture $r): string => (string) $r->ticket_url)
            ->openUrlInNewTab();
    }

    /**
     * Per-row recapture: dispatch RecaptureScreenshotJob for this card's
     * page so a single shot can be refreshed without re-running an
     * entire panel batch. Useful for chasing a flaky capture or
     * verifying a fix without going back to the regenerate modal.
     */
    protected static function recaptureAction(): Action
    {
        return Action::make('recapture')
            ->label('Recapture')
            ->color('gray')
            ->icon('heroicon-o-arrow-path')
            ->requiresConfirmation()
            ->modalHeading('Recapture this screenshot')
            ->modalDescription('Queues a single Playwright capture for this page. Refresh the grid in ~30 seconds to see the new shot.')
            ->modalSubmitActionLabel('Recapture')
            ->action(function (ScreenshotCapture $record): void {
                if ($record->screenshot_page_id === null) {
                    Notification::make()
                        ->title('Cannot recapture')
                        ->body('This capture is not linked to a page row.')
                        ->danger()
                        ->send();

                    return;
                }

                RecaptureScreenshotJob::dispatch(
                    pageId: $record->screenshot_page_id,
                    tag: (string) ($record->tag ?? 'latest'),
                );

                Notification::make()
                    ->title('Recapture queued')
                    ->body('A new shot is on the way.')
                    ->success()
                    ->send();
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => ListScreenshotCaptures::route('/'),
            'view' => ViewScreenshotCapture::route('/{record}'),
        ];
    }
}
