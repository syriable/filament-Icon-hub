<?php

declare(strict_types=1);

use BladeUI\Icons\Factory;
use Livewire\Livewire;
use Syriable\Filament\Plugins\IconHub\Actions\HideIcons;
use Syriable\Filament\Plugins\IconHub\Contracts\IconVisibility;
use Syriable\Filament\Plugins\IconHub\Facades\IconHub;
use Syriable\Filament\Plugins\IconHub\Forms\Components\IconPicker;
use Syriable\Filament\Plugins\IconHub\Forms\Components\IconSelect;
use Syriable\Filament\Plugins\IconHub\Models\HiddenIcon;
use Syriable\Filament\Plugins\IconHub\Registry\IconRegistry;
use Syriable\Filament\Plugins\IconHub\Tests\Fixtures\TestForm;

beforeEach(function () {
    // Two extra Blade Icons sets whose prefixes overlap ("fa" / "fa-brands"),
    // one of them with a nested directory, like many real icon packages.
    app(Factory::class)->add('test-fa', ['prefix' => 'fa', 'paths' => [fixturePath('blade/fa')]]);
    app(Factory::class)->add('test-fa-brands', ['prefix' => 'fa-brands', 'paths' => [fixturePath('blade/fa-brands')]]);
    app(IconRegistry::class)->rediscover();
});

describe('Blade Icons names', function () {
    it('resolves Blade Icons names', function (string $name, string $key) {
        expect(IconHub::find($name)?->key())->toBe($key);
    })->with([
        'heroicons outline' => ['heroicon-o-arrow-down-tray', 'heroicons:o-arrow-down-tray'],
        'heroicons solid' => ['heroicon-s-user', 'heroicons:s-user'],
        'nested directory' => ['fa-solid.star', 'test-fa:solid.star'],
        'root file' => ['fa-user', 'test-fa:user'],
        'longer prefix wins' => ['fa-brands-github', 'test-fa-brands:github'],
        'numeric file name' => ['fa-123', 'test-fa:123'],
    ]);

    it('still resolves legacy "provider:name" identifiers', function () {
        expect(IconHub::find('heroicons:o-arrow-down-tray')?->source->value)->toBe('heroicon-o-arrow-down-tray');
    });

    it('returns null for unknown names', function (string $name) {
        expect(IconHub::find($name))->toBeNull();
    })->with(['heroicon-o-nope', 'unknownprefix-user', 'heroicon', '']);

    it('stores Blade Icons by their Blade Icons name', function () {
        $icon = IconHub::find('heroicons:o-arrow-down-tray');

        expect(app(IconRegistry::class)->storedValue($icon))->toBe('heroicon-o-arrow-down-tray')
            ->and(app(IconRegistry::class)->normalizeValue('heroicons:o-arrow-down-tray'))->toBe('heroicon-o-arrow-down-tray')
            ->and(app(IconRegistry::class)->normalizeValue('fa-solid.star'))->toBe('fa-solid.star');
    });

    it('can store namespaced ids instead', function () {
        config()->set('icon-hub.blade_icons.store_as', 'id');

        expect(app(IconRegistry::class)->normalizeValue('heroicon-o-arrow-down-tray'))->toBe('heroicons:o-arrow-down-tray');
    });

    it('keeps "provider:name" for non Blade Icons providers', function () {
        expect(app(IconRegistry::class)->normalizeValue('brand:star'))->toBe('brand:star');
    });

    it('stored values render through Blade Icons and Filament directly', function () {
        $value = app(IconRegistry::class)->normalizeValue('heroicons:o-arrow-down-tray');

        expect(svg($value)->toHtml())->toContain('<svg')
            ->and(IconHub::html($value)->toHtml())->toContain('<svg');
    });
});

describe('Fields store Blade Icons names', function () {
    it('picker offers and saves Blade Icons names', function () {
        TestForm::$components = fn (): array => [IconPicker::make('icon')->providers(['heroicons'])];

        $component = Livewire::test(TestForm::class);
        $result = $component->call('callSchemaComponentMethod', 'form.icon', 'searchIcons', ['search' => 'arrow down tray'])->effects['returns'][0];

        expect(collect($result['icons'])->pluck('id'))->toContain('heroicon-o-arrow-down-tray');

        $component->fillForm(['icon' => 'heroicon-o-arrow-down-tray'])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertSet('saved', ['icon' => 'heroicon-o-arrow-down-tray']);
    });

    it('picker converts legacy stored values when the form loads', function () {
        TestForm::$components = fn (): array => [IconPicker::make('icon')->default('heroicons:o-arrow-down-tray')];

        Livewire::test(TestForm::class)
            ->assertFormSet(['icon' => 'heroicon-o-arrow-down-tray'])
            ->call('save')
            ->assertSet('saved', ['icon' => 'heroicon-o-arrow-down-tray']);
    });

    it('select offers and saves Blade Icons names', function () {
        TestForm::$components = fn (): array => [IconSelect::make('icon')->providers(['heroicons'])->multiple()->default(['heroicons:o-user'])];

        $component = Livewire::test(TestForm::class)->assertFormSet(['icon' => ['heroicon-o-user']]);
        $options = $component->call('callSchemaComponentMethod', 'form.icon', 'getSearchResultsForJs', ['search' => 'arrow down tray'])->effects['returns'][0];

        expect(collect($options)->pluck('value'))->toContain('heroicon-o-arrow-down-tray');

        $component->fillForm(['icon' => ['heroicon-o-user', 'fa-solid.star']])
            ->call('save')
            ->assertHasFormErrors(['icon']);

        $component->fillForm(['icon' => ['heroicon-o-user', 'heroicon-s-star']])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertSet('saved', ['icon' => ['heroicon-o-user', 'heroicon-s-star']]);
    });
});

describe('Hiding with Blade Icons names', function () {
    it('hides icons given by Blade Icons name', function () {
        config()->set('icon-hub.library.enabled', true);
        app()->forgetScopedInstances();

        app(HideIcons::class)->handle(['heroicon-o-arrow-down-tray']);

        expect(HiddenIcon::query()->pluck('icon')->all())->toBe(['heroicons:o-arrow-down-tray'])
            ->and(app(IconVisibility::class)->isHidden('heroicons:o-arrow-down-tray'))->toBeTrue();
    });

    it('accepts Blade Icons names in the hidden config list', function () {
        config()->set('icon-hub.hidden', ['heroicon-o-arrow-down-tray']);
        app()->forgetScopedInstances();

        expect(app(IconVisibility::class)->isHidden('heroicons:o-arrow-down-tray'))->toBeTrue();
    });
});
