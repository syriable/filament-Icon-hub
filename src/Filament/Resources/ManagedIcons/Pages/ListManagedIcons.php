<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\IconHub\Filament\Resources\ManagedIcons\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Syriable\Filament\Plugins\IconHub\Filament\Resources\ManagedIcons\ManagedIconResource;

class ListManagedIcons extends ListRecords
{
    protected static string $resource = ManagedIconResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
