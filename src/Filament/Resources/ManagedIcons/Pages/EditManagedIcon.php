<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\IconHub\Filament\Resources\ManagedIcons\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Syriable\Filament\Plugins\IconHub\Actions\SaveManagedIcon;
use Syriable\Filament\Plugins\IconHub\Data\ManagedIconData;
use Syriable\Filament\Plugins\IconHub\Filament\Resources\ManagedIcons\ManagedIconResource;
use Syriable\Filament\Plugins\IconHub\Models\ManagedIcon;

class EditManagedIcon extends EditRecord
{
    protected static string $resource = ManagedIconResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var ManagedIcon $record */
        $svg = ManagedIconResource::svgFromFormData($data);

        return app(SaveManagedIcon::class)->handle(
            ManagedIconData::fromArray([...$data, 'svg' => trim($svg) !== '' ? $svg : $record->svg]),
            $record,
        );
    }
}
