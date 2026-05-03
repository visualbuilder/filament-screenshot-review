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
                    ViewColumn::make('image')
                        ->view('filament-screenshot-review::columns.image-with-lightbox'),
                    TextColumn::make('title')
                        ->state(fn (ScreenshotCapture $r): string =>
                            $r->screenshotPage->panel . ' · ' .
                            ($r->screenshotPage->label ?? $r->screenshotPage->slug))
                        ->weight('bold'),
                    TextColumn::make('viewport_mode')
                        ->state(fn (ScreenshotCapture $r): string =>
                            $r->screenshotPage->viewport . '-' . $r->screenshotPage->mode)
                        ->badge()
                        ->color('gray'),
                    TextColumn::make('status')
                        ->badge(),
                ]),
            ])
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


    protected static function approveAction(): Action
    {
        return Action::make('approve')
            ->label('Approve')
            ->color('success')
            ->icon('heroicon-o-check')
            ->button()  // solid filled button instead of the default text-link
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
            ->button()  // solid filled button instead of the default text-link
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

    public static function getPages(): array
    {
        return [
            'index' => ListScreenshotCaptures::route('/'),
            'view' => ViewScreenshotCapture::route('/{record}'),
        ];
    }
}
