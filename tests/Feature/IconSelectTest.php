<?php

declare(strict_types=1);

use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Syriable\Filament\Plugins\IconHub\Facades\IconHub;
use Syriable\Filament\Plugins\IconHub\Forms\Components\IconSelect;
use Syriable\Filament\Plugins\IconHub\Tests\Fixtures\ArrayProvider;
use Syriable\Filament\Plugins\IconHub\Tests\Fixtures\TestForm;

function selectForm(Closure $configure): Testable
{
    TestForm::$components = fn (): array => [$configure(IconSelect::make('icon'))];

    return Livewire::test(TestForm::class);
}

beforeEach(function () {
    IconHub::register(new ArrayProvider('alpha', ['user', 'home', 'cog']));
    IconHub::register(new ArrayProvider('beta', ['user', 'star']));
});

describe('IconSelect', function () {
    it('renders as a searchable html select', function () {
        selectForm(fn (IconSelect $select) => $select->providers(['alpha']))
            ->assertSuccessful()
            ->assertSee('fi-fo-select', false);

        $select = IconSelect::make('icon');

        expect($select->isSearchable())->toBeTrue()
            ->and($select->isHtmlAllowed())->toBeTrue();
    });

    it('renders the selected icon as the initial label', function () {
        selectForm(fn (IconSelect $select) => $select->providers(['alpha'])->default('alpha:home'))
            ->assertSee('fi-icon-hub-option', false)
            ->assertFormSet(['icon' => 'alpha:home']);
    });

    it('saves a valid icon', function () {
        selectForm(fn (IconSelect $select) => $select->providers(['alpha', 'beta']))
            ->fillForm(['icon' => 'beta:star'])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertSet('saved', ['icon' => 'beta:star']);
    });

    it('rejects unknown icons and providers outside the allow-list', function (string $value) {
        selectForm(fn (IconSelect $select) => $select->providers(['alpha']))
            ->fillForm(['icon' => $value])
            ->call('save')
            ->assertHasFormErrors(['icon']);
    })->with([
        'unknown icon' => 'alpha:missing',
        'other provider' => 'beta:star',
        'malformed' => 'heroicon-o-user',
    ]);

    it('supports multiple selection', function () {
        selectForm(fn (IconSelect $select) => $select->providers(['alpha', 'beta'])->multiple())
            ->fillForm(['icon' => ['alpha:home', 'beta:star']])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertSet('saved', ['icon' => ['alpha:home', 'beta:star']]);
    });

    it('loads the first icons when the dropdown opens', function () {
        $options = selectForm(fn (IconSelect $select) => $select->providers(['alpha']))
            ->call('callSchemaComponentMethod', 'form.icon', 'getOptionsForJs')
            ->effects['returns'][0];

        expect(collect($options)->pluck('value')->all())->toBe(['alpha:user', 'alpha:home', 'alpha:cog'])
            ->and($options[0]['label'])->toContain('<svg')->toContain('fi-icon-hub-option-label');
    });

    it('searches across providers and groups results by provider', function () {
        $results = selectForm(fn (IconSelect $select) => $select->providers(['alpha', 'beta']))
            ->call('callSchemaComponentMethod', 'form.icon', 'getSearchResultsForJs', ['search' => 'user'])
            ->effects['returns'][0];

        expect(collect($results)->pluck('label')->all())->toBe(['Alpha', 'Beta'])
            ->and(collect($results[0]['options'])->pluck('value')->all())->toBe(['alpha:user'])
            ->and(collect($results[1]['options'])->pluck('value')->all())->toBe(['beta:user']);
    });

    it('can list results without provider groups', function () {
        $results = selectForm(fn (IconSelect $select) => $select->providers(['alpha', 'beta'])->groupByProvider(false))
            ->call('callSchemaComponentMethod', 'form.icon', 'getSearchResultsForJs', ['search' => 'user'])
            ->effects['returns'][0];

        expect(collect($results)->pluck('value')->all())->toBe(['alpha:user', 'beta:user']);
    });

    it('respects the options limit', function () {
        $options = selectForm(fn (IconSelect $select) => $select->providers(['alpha'])->optionsLimit(2))
            ->call('callSchemaComponentMethod', 'form.icon', 'getOptionsForJs')
            ->effects['returns'][0];

        expect($options)->toHaveCount(2);
    });

    it('escapes labels and applies extra icon attributes', function () {
        $label = IconSelect::make('icon')
            ->extraIconAttributes(['class' => 'my-icon'])
            ->getIconOptionLabel('heroicons:o-user');

        expect($label)->toContain('class="my-icon"')->toContain('>User</span>')
            ->toContain('fi-icon-hub-option-meta">Outline</span>')
            ->and(IconSelect::make('icon')->getIconOptionLabel('nope:x'))->toBeNull();
    });
});
