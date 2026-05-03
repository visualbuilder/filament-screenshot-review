<?php

declare(strict_types=1);

namespace Visualbuilder\FilamentScreenshotReview\Filament\Resources\ScreenshotCaptures;

use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Visualbuilder\FilamentScreenshotReview\Contracts\TicketSink;
use Visualbuilder\FilamentScreenshotReview\Enums\ScreenshotStatus;
use Visualbuilder\FilamentScreenshotReview\Filament\Resources\ScreenshotCaptures\Pages\ListScreenshotCaptures;
use Visualbuilder\FilamentScreenshotReview\Filament\Resources\ScreenshotCaptures\Pages\ViewScreenshotCapture;
use Visualbuilder\FilamentScreenshotReview\Models\ScreenshotCapture;
use Visualbuilder\FilamentScreenshotReview\Models\ScreenshotPage;

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
            ->columns([
                Stack::make([
                    ViewColumn::make('card')
                        ->view('filament-screenshot-review::columns.capture-card'),
                ]),
            ])
            ->filters([
                SelectFilter::make('panel')
                    ->options(fn () => static::panelOptions())
                    ->multiple()
                    ->query(function ($query, array $data) {
                        $values = $data['values'] ?? [];
                        if (empty($values)) {
                            return $query;
                        }

                        return $query->whereHas('screenshotPage',
                            fn ($q) => $q->whereIn('panel', $values));
                    }),
                SelectFilter::make('viewport')
                    ->options(['desktop' => 'Desktop', 'tablet' => 'Tablet', 'mobile' => 'Mobile'])
                    ->multiple()
                    ->query(function ($query, array $data) {
                        $values = $data['values'] ?? [];
                        if (empty($values)) {
                            return $query;
                        }

                        return $query->whereHas('screenshotPage',
                            fn ($q) => $q->whereIn('viewport', $values));
                    }),
                SelectFilter::make('mode')
                    ->options(['light' => 'Light', 'dark' => 'Dark'])
                    ->multiple()
                    ->query(function ($query, array $data) {
                        $values = $data['values'] ?? [];
                        if (empty($values)) {
                            return $query;
                        }

                        return $query->whereHas('screenshotPage',
                            fn ($q) => $q->whereIn('mode', $values));
                    }),
                SelectFilter::make('status')
                    ->options(ScreenshotStatus::class)
                    ->multiple()
                    ->default([ScreenshotStatus::PENDING->value]),
                SelectFilter::make('tag')
                    ->options(fn () => ScreenshotCapture::query()
                        ->distinct()
                        ->pluck('tag', 'tag')
                        ->all())
                    ->default('latest'),
            ])
            ->recordActions([
                static::approveAction(),
                static::requestChangesAction(),
                static::historyAction(),
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
     * @return array<string, string>
     */
    protected static function panelOptions(): array
    {
        if (class_exists(\Visualbuilder\FilamentPanelScreenshotCatalogue\PanelRegistry::class)) {
            return collect(\Visualbuilder\FilamentPanelScreenshotCatalogue\PanelRegistry::all())
                ->mapWithKeys(fn ($descriptor, $key) => [$key => ucfirst((string) $key)])
                ->all();
        }

        return ScreenshotPage::query()
            ->distinct()
            ->pluck('panel', 'panel')
            ->all();
    }

    protected static function approveAction(): Action
    {
        return Action::make('approve')
            ->label('Approve')
            ->color('success')
            ->icon('heroicon-o-check')
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
                Select::make('severity')
                    ->options([
                        'blocker' => 'Blocker',
                        'major' => 'Major',
                        'minor' => 'Minor',
                    ])
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

    protected static function historyAction(): Action
    {
        return Action::make('view_history')
            ->label('History')
            ->color('gray')
            ->icon('heroicon-o-clock')
            ->url(fn (ScreenshotCapture $r): string =>
                static::getUrl('view', ['record' => $r]));
    }

    protected static function viewTicketAction(): Action
    {
        return Action::make('view_ticket')
            ->label('View ticket')
            ->color('primary')
            ->icon('heroicon-o-arrow-top-right-on-square')
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
