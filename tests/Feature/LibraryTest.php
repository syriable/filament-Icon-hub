<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Syriable\Filament\Plugins\IconHub\Actions\HideIcons;
use Syriable\Filament\Plugins\IconHub\Actions\RestoreIcon;
use Syriable\Filament\Plugins\IconHub\Actions\SaveManagedIcon;
use Syriable\Filament\Plugins\IconHub\Contracts\IconVisibility;
use Syriable\Filament\Plugins\IconHub\Data\IconQuery;
use Syriable\Filament\Plugins\IconHub\Data\ManagedIconData;
use Syriable\Filament\Plugins\IconHub\Facades\IconHub;
use Syriable\Filament\Plugins\IconHub\Models\HiddenIcon;
use Syriable\Filament\Plugins\IconHub\Models\ManagedIcon;
use Syriable\Filament\Plugins\IconHub\Providers\LibraryIconProvider;
use Syriable\Filament\Plugins\IconHub\Registry\IconRegistry;

beforeEach(function () {
    config()->set('icon-hub.library.enabled', true);
    app(IconRegistry::class)->rediscover();
    app()->forgetScopedInstances();
});

describe('SaveManagedIcon', function () {
    it('stores sanitized svg and derives the name from the label', function () {
        $icon = app(SaveManagedIcon::class)->handle(new ManagedIconData(
            svg: '<svg xmlns="http://www.w3.org/2000/svg" onclick="steal()"><script>x</script><path d="M0 0"/></svg>',
            label: 'Company Logo',
            collection: '  Brand  ',
            tags: ['Logo', 'logo'],
        ));

        expect($icon->name)->toBe('company-logo')
            ->and($icon->collection)->toBe('Brand')
            ->and($icon->tags)->toBe(['logo'])
            ->and($icon->svg)->not->toContain('script')->not->toContain('onclick');
    });

    it('rejects markup that cannot be sanitized', function () {
        app(SaveManagedIcon::class)->handle(new ManagedIconData(svg: '<div>not svg</div>', label: 'Bad'));
    })->throws(ValidationException::class);

    it('rejects duplicate names', function () {
        ManagedIcon::factory()->create(['name' => 'logo']);

        app(SaveManagedIcon::class)->handle(new ManagedIconData(svg: '<svg xmlns="http://www.w3.org/2000/svg"><path d="M0 0"/></svg>', name: 'logo'));
    })->throws(ValidationException::class);

    it('updates an existing icon', function () {
        $icon = ManagedIcon::factory()->create(['name' => 'logo']);

        app(SaveManagedIcon::class)->handle(new ManagedIconData(svg: $icon->svg, label: 'New label', name: 'logo', isEnabled: false), $icon);

        expect($icon->refresh()->label)->toBe('New label')->and($icon->is_enabled)->toBeFalse();
    });
});

describe('LibraryIconProvider', function () {
    it('is discovered when the library is enabled', function () {
        expect(IconHub::provider('library'))->toBeInstanceOf(LibraryIconProvider::class);
    });

    it('searches enabled icons by name, label, collection and tags', function () {
        ManagedIcon::factory()->create(['name' => 'rocket', 'label' => 'Rocket', 'collection' => 'Space', 'tags' => ['launch']]);
        ManagedIcon::factory()->create(['name' => 'planet', 'label' => 'Planet', 'collection' => 'Space']);
        ManagedIcon::factory()->disabled()->create(['name' => 'rocket-old', 'label' => 'Old rocket']);

        $provider = IconHub::provider('library');

        expect(collect($provider->search(new IconQuery('rocket'))->icons)->pluck('name')->all())->toBe(['rocket'])
            ->and($provider->search(new IconQuery('launch'))->icons)->toHaveCount(1)
            ->and($provider->search(new IconQuery(category: 'Space'))->icons)->toHaveCount(2)
            ->and($provider->search(new IconQuery('100%'))->icons)->toHaveCount(0)
            ->and($provider->metadata()->categories)->toBe(['Space' => 'Space']);
    });

    it('paginates', function () {
        ManagedIcon::factory()->count(5)->create();

        $results = IconHub::provider('library')->search(new IconQuery(perPage: 2, page: 2));

        expect($results->icons)->toHaveCount(2)->and($results->hasMore)->toBeTrue();
    });

    it('still renders disabled icons that are already stored', function () {
        ManagedIcon::factory()->disabled()->create(['name' => 'legacy']);

        expect(IconHub::html('library:legacy')->toHtml())->toContain('<svg');
    });
});

describe('Hiding icons', function () {
    it('hides icons from any provider without touching the source', function () {
        $count = app(HideIcons::class)->handle(['heroicons:o-user', 'heroicons:o-user', 'invalid']);

        expect($count)->toBe(1)
            ->and(HiddenIcon::query()->pluck('icon')->all())->toBe(['heroicons:o-user'])
            ->and(app(IconVisibility::class)->isHidden('heroicons:o-user'))->toBeTrue()
            ->and(collect(IconHub::search('user', ['heroicons'])->icons)->map->key()->all())->not->toContain('heroicons:o-user')
            ->and(IconHub::find('heroicons:o-user'))->not->toBeNull();
    });

    it('restores hidden icons', function () {
        app(HideIcons::class)->handle(['heroicons:o-user']);

        expect(app(RestoreIcon::class)->handle('heroicons:o-user'))->toBeTrue()
            ->and(app(IconVisibility::class)->isHidden('heroicons:o-user'))->toBeFalse();
    });

    it('does not break the picker when the table is missing', function () {
        Schema::drop('icon_hub_hidden_icons');
        app()->forgetScopedInstances();

        expect(app(IconVisibility::class)->isHidden('heroicons:o-user'))->toBeFalse();
    });
});
