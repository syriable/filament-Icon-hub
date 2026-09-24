<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\IconHub\Registry;

use BladeUI\Icons\Factory as BladeIconFactory;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Container\Container;
use Syriable\Filament\Plugins\IconHub\Contracts\IconProvider;
use Syriable\Filament\Plugins\IconHub\Models\ManagedIcon;
use Syriable\Filament\Plugins\IconHub\Providers\BladeIconSetProvider;
use Syriable\Filament\Plugins\IconHub\Providers\LibraryIconProvider;
use Syriable\Filament\Plugins\IconHub\Providers\LocalSvgProvider;

/**
 * Builds the providers declared in config/icon-hub.php: Blade Icons sets,
 * local SVG directories, the uploaded-icon library and custom classes.
 *
 * @internal
 */
final readonly class ConfigProviderDiscovery
{
    public function __construct(
        private Container $container,
        private Config $config,
    ) {}

    /**
     * @return iterable<IconProvider>
     */
    public function __invoke(): iterable
    {
        yield from $this->bladeIconSets();
        yield from $this->localDirectories();
        yield from $this->library();
        yield from $this->customProviders();
    }

    /**
     * @return iterable<IconProvider>
     */
    private function bladeIconSets(): iterable
    {
        if (! $this->config->get('icon-hub.blade_icons.enabled', true) || ! $this->container->bound(BladeIconFactory::class)) {
            return;
        }

        /** @var array<string, array<string, mixed>> $sets */
        $sets = $this->container->make(BladeIconFactory::class)->all();
        $only = $this->config->get('icon-hub.blade_icons.sets');
        $except = (array) $this->config->get('icon-hub.blade_icons.except', []);
        $labels = (array) $this->config->get('icon-hub.blade_icons.labels', []);
        $variants = (array) $this->config->get('icon-hub.blade_icons.variants', []);

        $names = is_array($only) ? array_values(array_intersect($only, array_keys($sets))) : array_keys($sets);

        foreach ($names as $set) {
            if (in_array($set, $except, true)) {
                continue;
            }

            yield BladeIconSetProvider::fromSet(
                set: $set,
                options: $sets[$set],
                label: is_string($labels[$set] ?? null) ? $labels[$set] : null,
                variants: is_array($variants[$set] ?? null) ? $variants[$set] : [],
            );
        }
    }

    /**
     * @return iterable<IconProvider>
     */
    private function localDirectories(): iterable
    {
        foreach ((array) $this->config->get('icon-hub.local', []) as $id => $options) {
            if (! is_string($id) || ! is_array($options) || ! is_string($options['path'] ?? null)) {
                continue;
            }

            yield new LocalSvgProvider(
                id: $id,
                path: $options['path'],
                label: is_string($options['label'] ?? null) ? $options['label'] : null,
                variants: is_array($options['variants'] ?? null) ? $options['variants'] : [],
            );
        }
    }

    /**
     * @return iterable<IconProvider>
     */
    private function library(): iterable
    {
        if (! $this->config->get('icon-hub.library.enabled', false)) {
            return;
        }

        /** @var class-string<ManagedIcon> $model */
        $model = $this->config->get('icon-hub.library.models.icon', ManagedIcon::class);

        yield new LibraryIconProvider(
            id: (string) $this->config->get('icon-hub.library.provider_id', 'library'),
            label: (string) ($this->config->get('icon-hub.library.label') ?? __('icon-hub::icon-hub.library.provider_label')),
            model: $model,
        );
    }

    /**
     * @return iterable<IconProvider>
     */
    private function customProviders(): iterable
    {
        foreach ((array) $this->config->get('icon-hub.providers', []) as $provider) {
            $instance = is_string($provider) ? $this->container->make($provider) : $provider;

            if ($instance instanceof IconProvider) {
                yield $instance;
            }
        }
    }
}
