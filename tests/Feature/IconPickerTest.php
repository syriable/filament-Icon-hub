<?php

declare(strict_types=1);

use Livewire\Livewire;
use Syriable\Filament\Plugins\IconHub\Facades\IconHub;
use Syriable\Filament\Plugins\IconHub\Forms\Components\IconPicker;
use Syriable\Filament\Plugins\IconHub\Tests\Fixtures\ArrayProvider;
use Syriable\Filament\Plugins\IconHub\Tests\Fixtures\BrokenProvider;
use Syriable\Filament\Plugins\IconHub\Tests\Fixtures\TestForm;

function pickerForm(Closure $configure): \Livewire\Features\SupportTesting\Testable
{
    TestForm::$components = fn (): array => [$configure(IconPicker::make('icon'))];

    return Livewire::test(TestForm::class);
}

function pickerKey(): string
{
    return 'form.icon';
}

beforeEach(function () {
    IconHub::register(new ArrayProvider('alpha', ['user', 'home', 'cog']));
    IconHub::register(new ArrayProvider('beta', ['user', 'star']));
});

describe('IconPicker', function () {
    it('renders with the selected icon preview', function () {
        pickerForm(fn (IconPicker $picker) => $picker->default('alpha:user'))
            ->assertSuccessful()
            ->assertSee('icon-hub-picker', false)
            ->assertSee('alpha:user', false)
            ->assertFormSet(['icon' => 'alpha:user']);
    });

    it('hydrates and dehydrates a single value', function () {
        pickerForm(fn (IconPicker $picker) => $picker)
            ->fillForm(['icon' => 'alpha:home'])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertSet('saved', ['icon' => 'alpha:home']);
    });

    it('stores null when empty', function () {
        pickerForm(fn (IconPicker $picker) => $picker)
            ->fillForm(['icon' => ''])
            ->call('save')
            ->assertSet('saved', ['icon' => null]);
    });

    it('supports multiple selection', function () {
        pickerForm(fn (IconPicker $picker) => $picker->multiple())
            ->assertFormSet(['icon' => []])
            ->fillForm(['icon' => ['alpha:home', 'beta:star', 'alpha:home']])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertSet('saved', ['icon' => ['alpha:home', 'beta:star']]);
    });

    it('validates that the icon exists', function (string $value) {
        pickerForm(fn (IconPicker $picker) => $picker)
            ->fillForm(['icon' => $value])
            ->call('save')
            ->assertHasFormErrors(['icon']);
    })->with([
        'unknown icon' => 'alpha:missing',
        'unknown provider' => 'nope:user',
        'malformed' => 'heroicon-o-user',
    ]);

    it('rejects icons from providers that are not allowed', function () {
        pickerForm(fn (IconPicker $picker) => $picker->providers(['alpha']))
            ->fillForm(['icon' => 'beta:star'])
            ->call('save')
            ->assertHasFormErrors(['icon']);
    });

    it('validates each value of a multiple picker', function () {
        pickerForm(fn (IconPicker $picker) => $picker->multiple())
            ->fillForm(['icon' => ['alpha:home', 'alpha:missing']])
            ->call('save')
            ->assertHasFormErrors(['icon']);
    });

    it('supports required', function () {
        pickerForm(fn (IconPicker $picker) => $picker->required())
            ->call('save')
            ->assertHasFormErrors(['icon' => 'required']);
    });

    it('renders disabled without the modal', function () {
        pickerForm(fn (IconPicker $picker) => $picker->disabled())
            ->assertSuccessful()
            ->assertDontSee('fi-icon-hub-modal', false);
    });

    it('passes arbitrary attributes to the wrapper, trigger and icons', function () {
        pickerForm(fn (IconPicker $picker) => $picker
            ->default('alpha:user')
            ->extraAttributes(['data-icon-picker' => 'true'])
            ->extraTriggerAttributes(['data-trigger' => 'yes'])
            ->extraIconAttributes(['class' => 'my-icon', 'x-on:click' => 'pick()']))
            ->assertSee('data-icon-picker="true"', false)
            ->assertSee('data-trigger="yes"', false)
            ->assertSee('my-icon', false);
    });
});

describe('IconPicker::searchIcons', function () {
    it('returns a page of icons with rendered html', function () {
        $result = pickerForm(fn (IconPicker $picker) => $picker->providers(['alpha', 'beta']))
            ->call('callSchemaComponentMethod', pickerKey(), 'searchIcons', ['search' => 'user'])
            ->effects['returns'][0];

        expect(collect($result['icons'])->pluck('id')->all())->toBe(['alpha:user', 'beta:user'])
            ->and($result['icons'][0]['html'])->toContain('<svg')
            ->and($result['next'])->toBeNull()
            ->and($result['errors'])->toBe([]);
    });

    it('restricts results to one provider and returns its filters', function () {
        $result = pickerForm(fn (IconPicker $picker) => $picker)
            ->call('callSchemaComponentMethod', pickerKey(), 'searchIcons', ['provider' => 'beta', 'withFilters' => true])
            ->effects['returns'][0];

        expect(collect($result['icons'])->pluck('id')->all())->toBe(['beta:user', 'beta:star'])
            ->and($result['filters'])->toBe(['categories' => [], 'variants' => []]);
    });

    it('pages with a cursor', function () {
        $component = pickerForm(fn (IconPicker $picker) => $picker->providers(['alpha', 'beta'])->perPage(2));

        $first = $component->call('callSchemaComponentMethod', pickerKey(), 'searchIcons', [])->effects['returns'][0];
        $second = $component->call('callSchemaComponentMethod', pickerKey(), 'searchIcons', ['cursor' => $first['next']])->effects['returns'][0];

        expect($first['icons'])->toHaveCount(2)
            ->and($first['next'])->not->toBeNull()
            ->and(collect($second['icons'])->pluck('id')->all())->toBe(['alpha:cog', 'beta:user', 'beta:star']);
    });

    it('refuses providers outside the allow-list', function () {
        $result = pickerForm(fn (IconPicker $picker) => $picker->providers(['alpha']))
            ->call('callSchemaComponentMethod', pickerKey(), 'searchIcons', ['provider' => 'beta'])
            ->effects['returns'][0];

        expect($result['icons'])->toBe([]);
    });

    it('returns nothing when the field is disabled', function () {
        $result = pickerForm(fn (IconPicker $picker) => $picker->disabled())
            ->call('callSchemaComponentMethod', pickerKey(), 'searchIcons', [])
            ->effects['returns'][0];

        expect($result['icons'])->toBe([]);
    });

    it('reports broken providers with a safe message and keeps others working', function () {
        IconHub::register(new BrokenProvider);

        $result = pickerForm(fn (IconPicker $picker) => $picker->providers(['broken', 'beta']))
            ->call('callSchemaComponentMethod', pickerKey(), 'searchIcons', ['search' => 'star'])
            ->effects['returns'][0];

        expect($result['errors'])->toBe([[
            'provider' => 'broken',
            'label' => 'Broken',
            'message' => __('icon-hub::icon-hub.errors.unavailable'),
        ]])
            ->and(collect($result['icons'])->pluck('id')->all())->toBe(['beta:star']);
    });

    it('resolves previews for ids set on the server', function () {
        $result = pickerForm(fn (IconPicker $picker) => $picker->providers(['alpha']))
            ->call('callSchemaComponentMethod', pickerKey(), 'getIconsForJs', ['ids' => ['alpha:home', 'beta:star', 'bogus']])
            ->effects['returns'][0];

        expect(array_keys($result))->toBe(['alpha:home']);
    });
});

describe('IconPicker configuration', function () {
    it('shows the provider filter only when several providers are offered', function () {
        expect(IconPicker::make('a')->providers(['alpha', 'beta'])->shouldShowProviderFilter())->toBeTrue()
            ->and(IconPicker::make('a')->providers(['alpha'])->shouldShowProviderFilter())->toBeFalse()
            ->and(IconPicker::make('a')->providers(['alpha'])->showProviderFilter()->shouldShowProviderFilter())->toBeTrue();
    });

    it('uses configured default providers', function () {
        config()->set('icon-hub.picker.providers', ['beta', 'alpha']);

        expect(IconPicker::make('a')->getProviderIds())->toBe(['beta', 'alpha']);
    });

    it('merges extra icon attributes', function () {
        $picker = IconPicker::make('a')
            ->extraIconAttributes(['class' => 'a'])
            ->extraIconAttributes(['class' => 'b', 'data-x' => '1'], merge: true);

        expect($picker->getExtraIconAttributes())->toBe(['class' => 'a b', 'data-x' => '1']);
    });
});
