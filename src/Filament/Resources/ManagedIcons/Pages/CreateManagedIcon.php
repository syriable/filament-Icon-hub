<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\IconHub\Filament\Resources\ManagedIcons\Pages;

use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use Syriable\Filament\Plugins\IconHub\Actions\SaveManagedIcon;
use Syriable\Filament\Plugins\IconHub\Data\ManagedIconData;
use Syriable\Filament\Plugins\IconHub\Filament\Resources\ManagedIcons\ManagedIconResource;

class CreateManagedIcon extends CreateRecord
{
    protected static string $resource = ManagedIconResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $svg = ManagedIconResource::svgFromFormData($data);

        if (trim($svg) === '') {
            throw ValidationException::withMessages([
                'data.svg' => __('icon-hub::icon-hub.library.validation.svg_required'),
            ]);
        }

        return app(SaveManagedIcon::class)->handle(
            ManagedIconData::fromArray([...$data, 'svg' => $svg]),
        );
    }
}
