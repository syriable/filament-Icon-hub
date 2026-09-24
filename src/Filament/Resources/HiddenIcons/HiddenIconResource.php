<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\IconHub\Filament\Resources\HiddenIcons;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Syriable\Filament\Plugins\IconHub\Actions\RestoreIcon;
use Syriable\Filament\Plugins\IconHub\Models\HiddenIcon;
use Syriable\Filament\Plugins\IconHub\Tables\Columns\IconHubColumn;

/**
 * Icons hidden (ignored) from the picker. Hiding never modifies the source;
 * restoring makes the icon pickable again.
 */
class HiddenIconResource extends Resource
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEyeSlash;

    protected static ?string $recordTitleAttribute = 'icon';

    /**
     * @return class-string<HiddenIcon>
     */
    public static function getModel(): string
    {
        /** @var class-string<HiddenIcon> */
        return config('icon-hub.library.models.hidden_icon', HiddenIcon::class);
    }

    public static function getModelLabel(): string
    {
        return __('icon-hub::icon-hub.library.hidden.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('icon-hub::icon-hub.library.hidden.plural_label');
    }

    public static function getNavigationGroup(): ?string
    {
        $group = config('icon-hub.library.navigation_group');

        return is_string($group) ? $group : __('icon-hub::icon-hub.library.navigation_group');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                IconHubColumn::make('preview')
                    ->label(__('icon-hub::icon-hub.library.fields.preview'))
                    ->state(fn (HiddenIcon $record): string => $record->icon)
                    ->size('lg'),
                TextColumn::make('icon')
                    ->label(__('icon-hub::icon-hub.library.fields.icon'))
                    ->searchable()
                    ->copyable(),
                TextColumn::make('created_at')
                    ->label(__('icon-hub::icon-hub.library.fields.created_at'))
                    ->since()
                    ->sortable(),
            ])
            ->recordActions([
                Action::make('restore')
                    ->label(__('icon-hub::icon-hub.library.hidden.restore_action'))
                    ->icon(Heroicon::OutlinedEye)
                    ->action(function (HiddenIcon $record): void {
                        app(RestoreIcon::class)->handle($record->icon);

                        Notification::make()->success()->title(__('icon-hub::icon-hub.library.hidden.restored'))->send();
                    }),
            ])
            ->toolbarActions([
                BulkAction::make('restore')
                    ->label(__('icon-hub::icon-hub.library.hidden.restore_action'))
                    ->icon(Heroicon::OutlinedEye)
                    ->action(function (Collection $records): void {
                        foreach ($records as $record) {
                            /** @var HiddenIcon $record */
                            app(RestoreIcon::class)->handle($record->icon);
                        }
                    })
                    ->deselectRecordsAfterCompletion(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListHiddenIcons::route('/'),
        ];
    }
}
