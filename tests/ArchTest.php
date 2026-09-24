<?php

declare(strict_types=1);

arch('it uses strict types everywhere')
    ->expect('Syriable\Filament\Plugins\IconHub')
    ->toUseStrictTypes();

arch('it does not leave debugging statements')
    ->expect(['dd', 'dump', 'ray', 'var_dump', 'die'])
    ->not->toBeUsed();

arch('DTOs are final and readonly')
    ->expect('Syriable\Filament\Plugins\IconHub\Data')
    ->toBeFinal()
    ->toBeReadonly();

arch('value objects are final and readonly')
    ->expect('Syriable\Filament\Plugins\IconHub\ValueObjects')
    ->toBeFinal()
    ->toBeReadonly();

arch('actions are final')
    ->expect('Syriable\Filament\Plugins\IconHub\Actions')
    ->toBeFinal();

arch('contracts are interfaces')
    ->expect('Syriable\Filament\Plugins\IconHub\Contracts')
    ->toBeInterfaces();

arch('the core does not depend on Filament UI classes')
    ->expect([
        'Syriable\Filament\Plugins\IconHub\Data',
        'Syriable\Filament\Plugins\IconHub\Registry',
        'Syriable\Filament\Plugins\IconHub\Providers',
        'Syriable\Filament\Plugins\IconHub\Cache',
        'Syriable\Filament\Plugins\IconHub\Rendering',
    ])
    ->not->toUse(['Filament', 'Livewire']);

arch('DTOs do not depend on Eloquent')
    ->expect('Syriable\Filament\Plugins\IconHub\Data')
    ->not->toUse('Illuminate\Database');
