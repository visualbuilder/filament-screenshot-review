<?php

declare(strict_types=1);

namespace Visualbuilder\FilamentScreenshotReview\Filament\Resources\ScreenshotPages;

use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Artisan;
use Visualbuilder\FilamentScreenshotReview\Filament\Resources\ScreenshotPages\Pages\EditScreenshotPage;
use Visualbuilder\FilamentScreenshotReview\Filament\Resources\ScreenshotPages\Pages\ListScreenshotPages;
use Visualbuilder\FilamentScreenshotReview\Models\ScreenshotPage;

class ScreenshotPageResource extends Resource
{
    protected static ?string $model = ScreenshotPage::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-list-bullet';

    protected static ?string $navigationLabel = 'Pages';

    protected static string|\UnitEnum|null $navigationGroup = 'Screenshot Review';

    protected static ?int $navigationSort = 3;

    public static function form(\Filament\Schemas\Schema $schema): \Filament\Schemas\Schema
    {
        return $schema->components([
            TextInput::make('panel')->required()->maxLength(64),
            TextInput::make('slug')->required()->maxLength(255),
            TextInput::make('label')->maxLength(255),
            TextInput::make('url')->required()->maxLength(512),
            Select::make('viewport')
                ->options(['desktop' => 'Desktop', 'tablet' => 'Tablet', 'mobile' => 'Mobile'])
                ->required(),
            Select::make('mode')
                ->options(['light' => 'Light', 'dark' => 'Dark'])
                ->required(),
            TextInput::make('sort')->numeric()->default(1000),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->withCount('captures'))
            ->defaultSort('panel')
            ->columns([
                TextColumn::make('panel')->searchable()->sortable(),
                TextColumn::make('label')
                    ->state(fn (ScreenshotPage $r): string => $r->label ?? $r->slug)
                    ->searchable(['label', 'slug']),
                TextColumn::make('viewport_mode')
                    ->state(fn (ScreenshotPage $r): string => $r->viewport . '-' . $r->mode)
                    ->badge(),
                TextColumn::make('url')->limit(60)->copyable(),
                ToggleColumn::make('included'),
                TextColumn::make('captures_count')
                    ->label('Captures')
                    ->alignCenter(),
                TextColumn::make('latest_captured_at')
                    ->label('Last captured')
                    ->state(fn (ScreenshotPage $r) => $r->latestCapture?->captured_at)
                    ->since(),
                TextColumn::make('removed_at')
                    ->date()
                    ->color('danger')
                    ->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('panel')
                    ->options(fn () => static::panelOptions())
                    ->multiple(),
                TernaryFilter::make('included')
                    ->placeholder('All')
                    ->trueLabel('Included only')
                    ->falseLabel('Excluded only'),
                TernaryFilter::make('removed')
                    ->placeholder('Active only')
                    ->trueLabel('Removed only')
                    ->falseLabel('Active only')
                    ->default(false)
                    ->queries(
                        true: fn ($query) => $query->whereNotNull('removed_at'),
                        false: fn ($query) => $query->whereNull('removed_at'),
                        blank: fn ($query) => $query,
                    ),
            ], layout: FiltersLayout::AboveContent)
            ->headerActions([
                static::refreshAction(),
                static::addUrlAction(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    static::includeBulkAction(),
                    static::excludeBulkAction(),
                ]),
            ]);
    }

    /**
     * @return array<string, string>
     */
    protected static function panelOptions(): array
    {
        if (class_exists(\Visualbuilder\FilamentScreenshotCatalogue\PanelRegistry::class)) {
            return collect(\Visualbuilder\FilamentScreenshotCatalogue\PanelRegistry::all())
                ->mapWithKeys(fn ($descriptor, $key) => [$key => ucfirst((string) $key)])
                ->all();
        }

        return ScreenshotPage::query()
            ->distinct()
            ->pluck('panel', 'panel')
            ->all();
    }

    protected static function refreshAction(): Action
    {
        return Action::make('refresh_from_filament')
            ->label('Refresh from Filament')
            ->icon('heroicon-o-arrow-path')
            ->authorize(fn (): bool =>
                auth()->user()?->can('manage', ScreenshotPage::class) ?? true)
            ->action(function (): void {
                $exit = Artisan::call('screenshot-review:sync-pages');
                Notification::make()
                    ->title($exit === 0 ? 'Sitemap refreshed' : 'Sync failed')
                    ->{$exit === 0 ? 'success' : 'danger'}()
                    ->body(Artisan::output())
                    ->send();
            });
    }

    protected static function addUrlAction(): Action
    {
        return Action::make('add_url')
            ->label('Add URL')
            ->icon('heroicon-o-plus')
            ->authorize(fn (): bool =>
                auth()->user()?->can('create', ScreenshotPage::class) ?? true)
            ->schema([
                Select::make('panel')
                    ->options(fn () => static::panelOptions())
                    ->required(),
                TextInput::make('slug')
                    ->required()
                    ->maxLength(255)
                    ->regex('/^[a-z0-9.-]+$/')
                    ->placeholder('e.g. custom-report.preview'),
                TextInput::make('label')->maxLength(255),
                TextInput::make('url')
                    ->required()
                    ->maxLength(512)
                    ->startsWith('/'),
                Select::make('viewport')
                    ->options(['desktop' => 'Desktop', 'tablet' => 'Tablet', 'mobile' => 'Mobile'])
                    ->required()
                    ->default('desktop'),
                Select::make('mode')
                    ->options(['light' => 'Light', 'dark' => 'Dark'])
                    ->required()
                    ->default('light'),
            ])
            ->action(function (array $data): void {
                ScreenshotPage::create([
                    ...$data,
                    'included' => true,
                    'sort' => 9999,
                ]);

                Notification::make()
                    ->title('Custom URL added — included by default')
                    ->success()
                    ->send();
            });
    }

    protected static function includeBulkAction(): BulkAction
    {
        return BulkAction::make('include_selected')
            ->label('Include selected')
            ->color('success')
            ->icon('heroicon-o-eye')
            ->action(fn ($records) => $records->each->update(['included' => true]));
    }

    protected static function excludeBulkAction(): BulkAction
    {
        return BulkAction::make('exclude_selected')
            ->label('Exclude selected')
            ->color('warning')
            ->icon('heroicon-o-eye-slash')
            ->action(fn ($records) => $records->each->update(['included' => false]));
    }

    public static function getPages(): array
    {
        return [
            'index' => ListScreenshotPages::route('/'),
            'edit' => EditScreenshotPage::route('/{record}/edit'),
        ];
    }
}
