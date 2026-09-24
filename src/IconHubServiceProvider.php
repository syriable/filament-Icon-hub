<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\IconHub;

use BladeUI\Icons\Factory as BladeIconFactory;
use Filament\Support\Assets\AlpineComponent;
use Filament\Support\Assets\Css;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Blade;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Syriable\Filament\Plugins\IconHub\Cache\IconCache;
use Syriable\Filament\Plugins\IconHub\Commands\ClearIconCacheCommand;
use Syriable\Filament\Plugins\IconHub\Contracts\IconVisibility;
use Syriable\Filament\Plugins\IconHub\Models\HiddenIcon;
use Syriable\Filament\Plugins\IconHub\Registry\ConfigProviderDiscovery;
use Syriable\Filament\Plugins\IconHub\Registry\IconRegistry;
use Syriable\Filament\Plugins\IconHub\Rendering\IconRenderer;
use Syriable\Filament\Plugins\IconHub\Rendering\SvgSanitizer;
use Syriable\Filament\Plugins\IconHub\Visibility\DefaultIconVisibility;

final class IconHubServiceProvider extends PackageServiceProvider
{
    public const string PACKAGE = 'syriable/filament-icon-hub';

    public function configurePackage(Package $package): void
    {
        $package
            ->name('icon-hub')
            ->hasConfigFile()
            ->hasViews()
            ->hasTranslations()
            ->hasMigration('create_icon_hub_tables')
            ->hasCommand(ClearIconCacheCommand::class);
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(IconCache::class, static function (Application $app): IconCache {
            $ttl = (array) config('icon-hub.cache.ttl', []);
            $store = config('icon-hub.cache.store');

            return new IconCache(
                cache: $app->make(CacheFactory::class),
                enabled: (bool) config('icon-hub.cache.enabled', true),
                store: is_string($store) ? $store : null,
                prefix: (string) config('icon-hub.cache.prefix', 'icon-hub'),
                ttl: array_map(static fn (mixed $seconds): ?int => $seconds === null ? null : (int) $seconds, $ttl),
            );
        });

        $this->app->singleton(SvgSanitizer::class, static fn (Application $app): SvgSanitizer => new SvgSanitizer(
            maxBytes: (int) config('icon-hub.security.max_svg_bytes', 262_144),
        ));

        $this->app->singleton(IconRenderer::class, static fn (Application $app): IconRenderer => new IconRenderer(
            sanitizer: $app->make(SvgSanitizer::class),
            cache: $app->make(IconCache::class),
            bladeIcons: $app->make(BladeIconFactory::class),
            allowedUrlSchemes: array_values(array_map(
                strtolower(...),
                array_filter((array) config('icon-hub.security.allowed_url_schemes', ['https']), is_string(...)),
            )),
        ));

        // Scoped: hidden icons are re-read per request in long-running workers.
        $this->app->scoped(IconVisibility::class, static function (): IconVisibility {
            /** @var class-string<HiddenIcon> $model */
            $model = config('icon-hub.library.models.hidden_icon', HiddenIcon::class);

            return new DefaultIconVisibility(
                configured: array_values(array_filter((array) config('icon-hub.hidden', []), is_string(...))),
                model: config('icon-hub.library.enabled', false) ? $model : null,
            );
        });

        // The registry resolves the container lazily (Octane safe) instead of holding it.
        $this->app->singleton(IconRegistry::class, static fn (): IconRegistry => (new IconRegistry)
            ->discoverUsing(static fn (): iterable => app(ConfigProviderDiscovery::class)()));
    }

    public function packageBooted(): void
    {
        // <x-icon-hub::icon icon="heroicons:o-user" />
        Blade::componentNamespace('Syriable\\Filament\\Plugins\\IconHub\\View\\Components', 'icon-hub');

        FilamentAsset::register([
            AlpineComponent::make('icon-hub-picker', __DIR__.'/../resources/dist/icon-hub-picker.js'),
            Css::make('icon-hub', __DIR__.'/../resources/dist/icon-hub.css'),
        ], package: self::PACKAGE);
    }
}
