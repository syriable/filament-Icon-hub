<?php

declare(strict_types=1);

namespace Workbench\App\Filament\Pages;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Syriable\Filament\Plugins\IconHub\Forms\Components\IconPicker;
use Syriable\Filament\Plugins\IconHub\Forms\Components\IconSelect;

/**
 * Demo page used to exercise the picker in a browser during development.
 */
final class IconDemo extends Page
{
    protected static ?string $slug = 'icon-demo';

    protected string $view = 'filament-panels::pages.page';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill(['icon' => 'heroicons:o-home', 'select_icon' => 'heroicons:o-star']);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            $this->getFormContentComponent(),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Icon Hub')->schema([
                    IconPicker::make('icon')
                        ->label('Icon')
                        ->required()
                        ->helperText('Single selection across every provider.')
                        ->extraAttributes(['data-icon-picker' => 'single']),
                    IconPicker::make('icons')
                        ->label('Icons')
                        ->multiple()
                        ->providers(['heroicons', 'brand'])
                        ->extraIconAttributes(['data-demo' => 'yes']),
                    IconSelect::make('select_icon')
                        ->label('Icon (select)')
                        ->helperText('The same icons as a native searchable Select.'),
                    IconSelect::make('select_icons')
                        ->label('Icons (multiple select)')
                        ->multiple()
                        ->providers(['heroicons', 'brand']),
                    IconPicker::make('disabled_icon')
                        ->label('Disabled')
                        ->default('heroicons:o-lock-closed')
                        ->disabled(),
                ]),
            ]);
    }

    protected function getFormContentComponent(): \Filament\Schemas\Components\Component
    {
        return \Filament\Schemas\Components\Form::make([\Filament\Schemas\Components\EmbeddedSchema::make('form')])
            ->id('form')
            ->livewireSubmitHandler('save')
            ->footer([
                \Filament\Schemas\Components\Actions::make([
                    Action::make('save')->submit('save'),
                ]),
            ]);
    }

    public function save(): void
    {
        $state = $this->form->getState();

        Notification::make()->success()->title('Saved: '.json_encode($state))->send();
    }
}
