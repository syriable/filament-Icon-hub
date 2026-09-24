<?php

declare(strict_types=1);

use Syriable\Filament\Plugins\IconHub\Data\IconQuery;
use Syriable\Filament\Plugins\IconHub\Enums\ProviderStatus;
use Syriable\Filament\Plugins\IconHub\Facades\IconHub;
use Syriable\Filament\Plugins\IconHub\Tests\Fixtures\ArrayProvider;
use Syriable\Filament\Plugins\IconHub\Tests\Fixtures\BrokenProvider;

beforeEach(function () {
    IconHub::register(new ArrayProvider('alpha', ['user', 'user-group', 'home', 'arrow-left', 'arrow-right']));
    IconHub::register(new ArrayProvider('beta', ['user', 'house', 'cog']));
});

describe('Search', function () {
    it('searches a single provider by name, label and tags', function () {
        $page = IconHub::search('user', ['alpha']);

        expect(array_map(fn ($icon) => $icon->key(), $page->icons))->toBe(['alpha:user', 'alpha:user-group'])
            ->and($page->nextCursor)->toBeNull();
    });

    it('requires every search term to match', function () {
        expect(IconHub::search('arrow left', ['alpha'])->icons)->toHaveCount(1);
    });

    it('searches across providers and keeps same-named icons apart', function () {
        $keys = array_map(fn ($icon) => $icon->key(), IconHub::search('user', ['alpha', 'beta'])->icons);

        expect($keys)->toBe(['alpha:user', 'alpha:user-group', 'beta:user']);
    });

    it('filters by provider', function () {
        expect(IconHub::search('', ['beta'])->icons)->toHaveCount(3);
    });

    it('paginates within and across providers with a cursor', function () {
        $query = new IconQuery(perPage: 3);

        $first = IconHub::search($query, ['alpha', 'beta']);
        $second = IconHub::search($query, ['alpha', 'beta'], $first->nextCursor);
        $third = IconHub::search($query, ['alpha', 'beta'], $second->nextCursor);

        expect($first->icons)->toHaveCount(3)
            ->and($first->nextCursor)->toBe('0.2')
            ->and($second->icons)->toHaveCount(5)
            ->and($second->nextCursor)->toBeNull()
            ->and($third->icons)->toHaveCount(3);

        $all = array_map(fn ($icon) => $icon->key(), [...$first->icons, ...$second->icons]);
        expect($all)->toHaveCount(8)->and(array_unique($all))->toHaveCount(8);
    });

    it('returns an empty page when nothing matches', function () {
        $page = IconHub::search('zzz-nothing', ['alpha', 'beta']);

        expect($page->icons)->toBe([])->and($page->nextCursor)->toBeNull()->and($page->errors)->toBe([]);
    });

    it('ignores malformed cursors', function () {
        expect(IconHub::search('', ['beta'], 'garbage')->icons)->toHaveCount(3);
    });

    it('isolates failing providers and reports them', function () {
        IconHub::register(new BrokenProvider);

        $page = IconHub::search('user', ['broken', 'beta']);

        expect($page->errors)->toBe(['broken' => 'unavailable'])
            ->and(array_map(fn ($icon) => $icon->key(), $page->icons))->toBe(['beta:user']);
    });

    it('reports unconfigured providers only when searched directly', function () {
        IconHub::register(new ArrayProvider('offline', ['user'], ProviderStatus::Unconfigured));

        expect(IconHub::search('user', ['offline', 'beta'])->errors)->toBe([])
            ->and(IconHub::search('user', ['offline'])->errors)->toBe(['offline' => 'unconfigured']);
    });

    it('removes hidden icons from results', function () {
        config()->set('icon-hub.hidden', ['alpha:user']);
        app()->forgetScopedInstances();

        $keys = array_map(fn ($icon) => $icon->key(), IconHub::search('user', ['alpha'])->icons);

        expect($keys)->toBe(['alpha:user-group'])
            ->and(IconHub::find('alpha:user'))->not->toBeNull();
    });
});
