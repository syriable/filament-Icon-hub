<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\IconHub\Tests\Fixtures;

use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Livewire\Component;
use Syriable\Filament\Plugins\IconHub\Models\HiddenIcon;
use Syriable\Filament\Plugins\IconHub\Tables\Columns\IconHubColumn;

final class TestTable extends Component implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    public function table(Table $table): Table
    {
        return $table
            ->query(HiddenIcon::query())
            ->columns([
                IconHubColumn::make('icon')->placeholder('No icon'),
                IconHubColumn::make('icon_with_label')
                    ->state(fn (HiddenIcon $record): string => $record->icon)
                    ->showLabel(),
            ]);
    }

    public function render(): string
    {
        return '<div>{{ $this->table }}</div>';
    }
}
