<?php

declare(strict_types=1);

use Illuminate\Contracts\Cache\Factory;
use Syriable\Filament\Plugins\IconHub\Cache\IconCache;
use Syriable\Filament\Plugins\IconHub\Facades\IconHub;
use Syriable\Filament\Plugins\IconHub\Tests\Fixtures\ArrayProvider;

describe('IconCache', function () {
    it('produces deterministic keys regardless of parameter order', function () {
        $cache = app(IconCache::class);

        expect($cache->key('p', 'search', ['a' => 1, 'b' => 2]))->toBe($cache->key('p', 'search', ['b' => 2, 'a' => 1]))
            ->and($cache->key('p', 'search', ['search' => 'user']))->not->toBe($cache->key('p', 'search', ['search' => 'home']))
            ->and($cache->key('p', 'search', []))->not->toBe($cache->key('q', 'search', []))
            ->and($cache->key('p', 'search', []))->not->toBe($cache->key('p', 'icon', []));
    });

    it('caches callback results', function () {
        $cache = app(IconCache::class);
        $calls = 0;

        $cache->remember('p', 'search', ['q' => 'user'], function () use (&$calls) {
            $calls++;

            return 'a';
        });
        $value = $cache->remember('p', 'search', ['q' => 'user'], function () use (&$calls) {
            $calls++;

            return 'b';
        });

        expect($value)->toBe('a')->and($calls)->toBe(1);
    });

    it('does not return results cached for a different search', function () {
        $cache = app(IconCache::class);

        $cache->remember('p', 'search', ['q' => 'user'], fn () => 'user results');

        expect($cache->remember('p', 'search', ['q' => 'home'], fn () => 'home results'))->toBe('home results');
    });

    it('flushes a single provider', function () {
        $cache = app(IconCache::class);
        $cache->put('p', 'icon', ['a'], 'p-value');
        $cache->put('q', 'icon', ['a'], 'q-value');

        $cache->flush('p');

        expect($cache->get('p', 'icon', ['a']))->toBeNull()
            ->and($cache->get('q', 'icon', ['a']))->toBe('q-value');
    });

    it('flushes everything', function () {
        $cache = app(IconCache::class);
        $cache->put('p', 'icon', ['a'], 'value');

        $cache->flush();

        expect($cache->get('p', 'icon', ['a']))->toBeNull();
    });

    it('bypasses the store when disabled', function () {
        $cache = new IconCache(app(Factory::class), enabled: false);
        $calls = 0;

        $cache->remember('p', 'x', [], function () use (&$calls) {
            return ++$calls;
        });
        $cache->remember('p', 'x', [], function () use (&$calls) {
            return ++$calls;
        });

        expect($calls)->toBe(2);
    });

    it('uses the configured ttl per type', function () {
        $this->travelTo(now());
        $cache = new IconCache(app(Factory::class), ttl: ['search' => 10]);
        $cache->put('p', 'search', [], 'value');

        $this->travel(11)->seconds();

        expect($cache->get('p', 'search', []))->toBeNull();
    });
});

describe('icon-hub:clear', function () {
    it('clears a single known provider', function () {
        IconHub::register(new ArrayProvider('cached', ['a']));

        $this->artisan('icon-hub:clear', ['provider' => 'cached'])->assertSuccessful();
    });

    it('fails for an unknown provider', function () {
        $this->artisan('icon-hub:clear', ['provider' => 'nope'])->assertFailed();
    });
});
