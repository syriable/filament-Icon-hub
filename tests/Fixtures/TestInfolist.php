<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\IconHub\Tests\Fixtures;

use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Livewire\Component;
use Syriable\Filament\Plugins\IconHub\Infolists\Components\IconHubEntry;

final class TestInfolist extends Component implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->state(['icon' => 'heroicons:o-home', 'icons' => ['heroicons:o-user', 'brand:star']])
            ->components([
                IconHubEntry::make('icon')->size('lg'),
                IconHubEntry::make('icons')->showLabel(),
            ]);
    }

    public function render(): string
    {
        return '<div>{{ $this->infolist }}</div>';
    }
}
