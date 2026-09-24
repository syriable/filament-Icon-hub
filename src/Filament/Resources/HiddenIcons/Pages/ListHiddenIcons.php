<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\IconHub\Filament\Resources\HiddenIcons\Pages;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Syriable\Filament\Plugins\IconHub\Actions\HideIcons;
use Syriable\Filament\Plugins\IconHub\Filament\Resources\HiddenIcons\HiddenIconResource;
use Syriable\Filament\Plugins\IconHub\Forms\Components\IconPicker;

class ListHiddenIcons extends ListRecords
{
    protected static string $resource = HiddenIconResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('hide')
                ->label(__('icon-hub::icon-hub.library.hidden.hide_action'))
                ->icon(Heroicon::OutlinedEyeSlash)
                ->schema([
                    IconPicker::make('icons')
                        ->label(__('icon-hub::icon-hub.library.fields.icons'))
                        ->multiple()
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $count = app(HideIcons::class)->handle((array) ($data['icons'] ?? []));

                    Notification::make()
                        ->success()
                        ->title(trans_choice('icon-hub::icon-hub.library.hidden.hidden_count', $count, ['count' => $count]))
                        ->send();
                }),
        ];
    }
}
