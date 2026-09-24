<?php

declare(strict_types=1);

use Syriable\Filament\Plugins\IconHub\Data\IconQuery;
use Syriable\Filament\Plugins\IconHub\Enums\SourceKind;
use Syriable\Filament\Plugins\IconHub\Facades\IconHub;
use Syriable\Filament\Plugins\IconHub\Providers\LocalSvgProvider;
use Syriable\Filament\Plugins\IconHub\Tests\Fixtures\ArrayProvider;

describe('BladeIconSetProvider', function () {
    it('exposes variants from configured prefixes', function () {
        $metadata = IconHub::metadata('heroicons');

        expect($metadata->variants)->toHaveKeys(['outline', 'solid', 'mini', 'micro'])
            ->and($metadata->variants['outline'])->toBe('Outline')
            ->and($metadata->categories)->toBe([])
            ->and($metadata->total)->toBeGreaterThan(1000);
    });

    it('filters by variant and strips the prefix from labels', function () {
        $results = IconHub::provider('heroicons')->search(new IconQuery('user', variant: 'solid'));

        expect($results->icons)->not->toBeEmpty()
            ->and(collect($results->icons)->every(fn ($icon) => $icon->variant === 'solid' && str_starts_with($icon->name, 's-')))->toBeTrue()
            ->and($results->icons[0]->label)->toBe('User')
            ->and($results->icons[0]->source->kind)->toBe(SourceKind::Blade)
            ->and($results->icons[0]->source->value)->toBe('heroicon-s-user');
    });

    it('lists the configured primary variant first when unfiltered', function () {
        $first = IconHub::provider('heroicons')->search(new IconQuery(perPage: 5))->icons;

        expect(collect($first)->every(fn ($icon) => $icon->variant === 'outline'))->toBeTrue();
    });

    it('ranks exact matches first', function () {
        $first = IconHub::provider('heroicons')->search(new IconQuery('user', variant: 'outline'))->icons[0];

        expect($first->name)->toBe('o-user');
    });
});

describe('LocalSvgProvider', function () {
    it('indexes nested directories as categories', function () {
        $provider = new LocalSvgProvider('local-test', fixturePath('icons'));
        $icon = $provider->find('brand/social/github');

        expect($icon?->category)->toBe('brand/social')
            ->and($icon?->label)->toBe('Github')
            ->and($icon?->source->kind)->toBe(SourceKind::SvgFile)
            ->and($provider->metadata()->categories)->toBe(['brand' => 'Brand', 'brand/social' => 'Brand/Social'])
            ->and($provider->search(new IconQuery(category: 'brand/social'))->icons)->toHaveCount(1)
            ->and($provider->search(new IconQuery('social'))->icons)->toHaveCount(1);
    });

    it('never resolves paths outside the index', function () {
        $provider = new LocalSvgProvider('local-test', fixturePath('icons/brand'));

        expect($provider->find('../star'))->toBeNull()
            ->and($provider->find('/etc/passwd'))->toBeNull();
    });

    it('returns no icons for a missing directory', function () {
        expect((new LocalSvgProvider('missing', '/does/not/exist'))->search(new IconQuery)->icons)->toBe([]);
    });
});

describe('IndexedIconProvider cache', function () {
    it('builds the index once and serves it from cache', function () {
        $provider = new ArrayProvider('cached', ['a', 'b']);
        $provider->search(new IconQuery);

        $fresh = new ArrayProvider('cached', ['a', 'b']);
        $fresh->search(new IconQuery('a'));
        $fresh->find('b');

        expect($provider->builds)->toBe(1)->and($fresh->builds)->toBe(0);
    });

    it('rebuilds the index after the cache is cleared', function () {
        (new ArrayProvider('cached', ['a']))->search(new IconQuery);

        $this->artisan('icon-hub:clear')->assertSuccessful();

        $fresh = new ArrayProvider('cached', ['a']);
        $fresh->search(new IconQuery);

        expect($fresh->builds)->toBe(1);
    });
});
