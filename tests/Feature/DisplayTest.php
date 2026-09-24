<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;
use Livewire\Livewire;
use Syriable\Filament\Plugins\IconHub\Facades\IconHub;
use Syriable\Filament\Plugins\IconHub\IconHubPlugin;
use Syriable\Filament\Plugins\IconHub\Models\HiddenIcon;
use Syriable\Filament\Plugins\IconHub\Tests\Fixtures\TestInfolist;
use Syriable\Filament\Plugins\IconHub\Tests\Fixtures\TestTable;

describe('Rendering', function () {
    it('renders blade icons with escaped custom attributes', function () {
        $html = IconHub::html('heroicons:o-user', ['class' => 'h-5 w-5', 'data-x' => '"><script>', 'x-on:click' => 'go()'])->toHtml();

        expect($html)->toStartWith('<svg')
            ->toContain('class="h-5 w-5"')
            ->toContain('data-x="&quot;&gt;&lt;script&gt;"')
            ->toContain('x-on:click="go()"')
            ->toContain('aria-hidden="true"');
    });

    it('does not hide icons that have an accessible name', function () {
        expect(IconHub::html('heroicons:o-user', ['aria-label' => 'User'])->toHtml())->not->toContain('aria-hidden');
    });

    it('sanitizes local svg files', function () {
        expect(IconHub::html('brand:brand/evil')->toHtml())->not->toContain('script')->not->toContain('onload');
    });

    it('renders nothing for unknown icons', function () {
        expect(IconHub::html('heroicons:nope')->toHtml())->toBe('')
            ->and(IconHub::html(null)->toHtml())->toBe('');
    });

    it('provides a blade component', function () {
        expect(Blade::render('<x-icon-hub::icon icon="heroicons:o-user" class="size-4" />'))->toContain('class="size-4"');
    });

    it('returns htmlable output usable in Filament icon methods', function () {
        expect(IconHub::html('heroicons:o-user'))->toBeInstanceOf(Illuminate\Contracts\Support\Htmlable::class);
    });
});

describe('IconHubColumn', function () {
    it('renders icons in table rows', function () {
        HiddenIcon::query()->create(['icon' => 'heroicons:o-user']);
        HiddenIcon::query()->create(['icon' => 'unknown:thing']);

        Livewire::test(TestTable::class)
            ->assertSuccessful()
            ->assertSee('fi-ta-icon-hub', false)
            ->assertSee('role="img"', false)
            ->assertSee('fi-icon-hub-display-label', false)
            ->assertSee('No icon');
    });
});

describe('IconHubEntry', function () {
    it('renders single and multiple icons', function () {
        Livewire::test(TestInfolist::class)
            ->assertSuccessful()
            ->assertSee('fi-in-icon-hub', false)
            ->assertSee('fi-size-lg', false)
            ->assertSee('Star');
    });
});

describe('IconHubPlugin', function () {
    it('only manages the library when enabled in config', function () {
        $plugin = IconHubPlugin::make()->manageLibrary();

        expect($plugin->getId())->toBe('icon-hub')
            ->and($plugin->managesLibrary())->toBeFalse();

        config()->set('icon-hub.library.enabled', true);

        expect($plugin->managesLibrary())->toBeTrue();
    });
});
