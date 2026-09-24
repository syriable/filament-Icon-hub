<?php

declare(strict_types=1);

use Syriable\Filament\Plugins\IconHub\Contracts\IconProvider;
use Syriable\Filament\Plugins\IconHub\Enums\ProviderStatus;
use Syriable\Filament\Plugins\IconHub\Exceptions\ProviderNotFound;
use Syriable\Filament\Plugins\IconHub\Facades\IconHub;
use Syriable\Filament\Plugins\IconHub\Providers\BladeIconSetProvider;
use Syriable\Filament\Plugins\IconHub\Providers\LocalSvgProvider;
use Syriable\Filament\Plugins\IconHub\Registry\IconRegistry;
use Syriable\Filament\Plugins\IconHub\Tests\Fixtures\ArrayProvider;
use Syriable\Filament\Plugins\IconHub\Tests\Fixtures\BrokenProvider;

describe('IconRegistry', function () {
    it('discovers blade icon sets and configured local directories', function () {
        expect(IconHub::provider('heroicons'))->toBeInstanceOf(BladeIconSetProvider::class)
            ->and(IconHub::provider('brand'))->toBeInstanceOf(LocalSvgProvider::class)
            ->and(IconHub::provider('brand')->label())->toBe('Brand');
    });

    it('registers and looks up custom providers', function () {
        IconHub::register(new ArrayProvider('company', ['logo']));

        expect(IconHub::has('company'))->toBeTrue()
            ->and(IconHub::provider('company'))->toBeInstanceOf(IconProvider::class)
            ->and(array_key_last(IconHub::all()))->toBe('company');
    });

    it('resolves provider classes through the container', function () {
        config()->set('icon-hub.providers', [BrokenProvider::class]);
        app(IconRegistry::class)->rediscover();

        expect(IconHub::has('broken'))->toBeTrue();
    });

    it('lets explicit registrations override discovered providers', function () {
        IconHub::register(new ArrayProvider('heroicons', ['custom']));

        expect(IconHub::find('heroicons:custom'))->not->toBeNull()
            ->and(IconHub::find('heroicons:o-user'))->toBeNull();
    });

    it('removes providers', function () {
        IconHub::forget('brand');

        expect(IconHub::has('brand'))->toBeFalse()
            ->and(fn () => IconHub::provider('brand'))->toThrow(ProviderNotFound::class);
    });

    it('filters and orders providers by id', function () {
        expect(array_keys(IconHub::providers(['brand', 'missing', 'heroicons'])))->toBe(['brand', 'heroicons']);
    });

    it('respects the blade icon set allow-list', function () {
        config()->set('icon-hub.blade_icons.sets', ['heroicons']);
        config()->set('icon-hub.blade_icons.labels', ['heroicons' => 'Hero']);
        app(IconRegistry::class)->rediscover();

        expect(IconHub::provider('heroicons')->label())->toBe('Hero');

        config()->set('icon-hub.blade_icons.except', ['heroicons']);
        app(IconRegistry::class)->rediscover();

        expect(IconHub::has('heroicons'))->toBeFalse();
    });

    it('rejects providers with invalid ids', function () {
        IconHub::register(new ArrayProvider('Not Valid', []));
    })->throws(InvalidArgumentException::class);

    it('resolves icons by identifier and returns null for anything unknown', function () {
        expect(IconHub::find('heroicons:o-user')?->label)->toBe('User')
            ->and(IconHub::find('heroicons:does-not-exist'))->toBeNull()
            ->and(IconHub::find('unknown:o-user'))->toBeNull()
            ->and(IconHub::find('not-an-id'))->toBeNull()
            ->and(IconHub::find(null))->toBeNull();
    });

    it('never lets a broken provider throw', function () {
        IconHub::register(new BrokenProvider);

        expect(IconHub::find('broken:anything'))->toBeNull()
            ->and(IconHub::metadata('broken')->categories)->toBe([])
            ->and(IconHub::html('broken:anything')->toHtml())->toBe('');
    });

    it('skips unavailable providers when resolving icons', function () {
        IconHub::register(new ArrayProvider('offline', ['a'], ProviderStatus::Unconfigured));

        expect(IconHub::find('offline:a'))->toBeNull()
            ->and(IconHub::status('offline'))->toBe(ProviderStatus::Unconfigured);
    });
});
