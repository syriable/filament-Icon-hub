<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\IconHub\Registry;

use Closure;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;
use InvalidArgumentException;
use Syriable\Filament\Plugins\IconHub\Contracts\IconProvider;
use Syriable\Filament\Plugins\IconHub\Data\Icon;
use Syriable\Filament\Plugins\IconHub\Data\IconQuery;
use Syriable\Filament\Plugins\IconHub\Data\ProviderMetadata;
use Syriable\Filament\Plugins\IconHub\Data\SearchPage;
use Syriable\Filament\Plugins\IconHub\Enums\ProviderStatus;
use Syriable\Filament\Plugins\IconHub\Enums\SourceKind;
use Syriable\Filament\Plugins\IconHub\Exceptions\ProviderNotFound;
use Syriable\Filament\Plugins\IconHub\Providers\BladeIconSetProvider;
use Syriable\Filament\Plugins\IconHub\Rendering\IconRenderer;
use Syriable\Filament\Plugins\IconHub\ValueObjects\IconId;
use Throwable;

/**
 * Coordinates icon providers: registration, lazy discovery, lookup and
 * rendering. Every call into a provider is isolated so that a failing
 * provider can never break the caller.
 */
final class IconRegistry
{
    /** Upper bound of the per-process lookup memo (keeps long-running workers lean). */
    private const int MAX_RESOLVED = 1000;

    /** @var array<string, IconProvider> */
    private array $registered = [];

    /** @var array<string, IconProvider> */
    private array $discovered = [];

    /** @var array<string, true> */
    private array $forgotten = [];

    /** @var list<Closure(self): iterable<IconProvider>> */
    private array $discoverers = [];

    private bool $hasDiscovered = false;

    /** @var array<string, Icon|null> */
    private array $resolved = [];

    /**
     * Register a provider instance or a container-resolvable provider class.
     * A provider registered with an existing id replaces the previous one.
     *
     * @param  IconProvider|class-string<IconProvider>  $provider
     */
    public function register(IconProvider|string $provider): self
    {
        if (is_string($provider)) {
            $provider = app($provider);
        }

        if (! $provider instanceof IconProvider) {
            throw new InvalidArgumentException('Icon providers must implement '.IconProvider::class.'.');
        }

        $id = $provider->id();

        if (! IconId::isValidProvider($id)) {
            throw new InvalidArgumentException("[{$id}] is not a valid icon provider id.");
        }

        unset($this->forgotten[$id]);
        $this->registered[$id] = $provider;
        $this->forgetResolved($id);

        return $this;
    }

    public function forget(string $id): self
    {
        unset($this->registered[$id], $this->discovered[$id]);
        $this->forgotten[$id] = true;
        $this->forgetResolved($id);

        return $this;
    }

    /**
     * Add a callback that returns providers. Discoverers run lazily, once,
     * the first time providers are read.
     *
     * @param  Closure(self): iterable<IconProvider>  $discoverer
     */
    public function discoverUsing(Closure $discoverer): self
    {
        $this->discoverers[] = $discoverer;
        $this->hasDiscovered = false;

        return $this;
    }

    /**
     * Re-run discovery on the next read (e.g. after config changes).
     */
    public function rediscover(): self
    {
        $this->discovered = [];
        $this->hasDiscovered = false;
        $this->resolved = [];

        return $this;
    }

    public function has(string $id): bool
    {
        return array_key_exists($id, $this->all());
    }

    public function provider(string $id): IconProvider
    {
        return $this->all()[$id] ?? throw ProviderNotFound::make($id);
    }

    /**
     * All providers in display order: discovered first, then explicitly
     * registered ones. An explicit registration overrides a discovered
     * provider with the same id.
     *
     * @return array<string, IconProvider>
     */
    public function all(): array
    {
        $this->discover();

        return array_merge($this->discovered, $this->registered);
    }

    /**
     * Providers restricted to (and ordered by) the given ids. Unknown ids are
     * ignored. Null returns every provider.
     *
     * @param  list<string>|null  $only
     * @return array<string, IconProvider>
     */
    public function providers(?array $only = null): array
    {
        $all = $this->all();

        if ($only === null) {
            return $all;
        }

        $providers = [];

        foreach ($only as $id) {
            if (isset($all[$id])) {
                $providers[$id] = $all[$id];
            }
        }

        return $providers;
    }

    public function status(string $id): ProviderStatus
    {
        try {
            return $this->provider($id)->status();
        } catch (Throwable $exception) {
            report($exception);

            return ProviderStatus::Unavailable;
        }
    }

    public function metadata(string $id): ProviderMetadata
    {
        try {
            return $this->provider($id)->metadata();
        } catch (Throwable $exception) {
            report($exception);

            return new ProviderMetadata;
        }
    }

    /**
     * Resolve an icon from a stored value: either a Blade Icons name
     * ("heroicon-o-user") or a "provider:name" identifier. Never throws:
     * invalid values, unknown providers and failing providers return null.
     */
    public function find(IconId|string|null $id): ?Icon
    {
        $iconId = $id instanceof IconId ? $id : IconId::tryParse($id);

        if ($iconId === null) {
            return is_string($id) ? $this->findByBladeName($id) : null;
        }

        $key = (string) $iconId;

        if (array_key_exists($key, $this->resolved)) {
            return $this->resolved[$key];
        }

        try {
            $provider = $this->provider($iconId->provider);
            $icon = $provider->status()->isAvailable() ? $provider->find($iconId->name) : null;
        } catch (Throwable $exception) {
            report($exception);

            // Do not memoize failures: the provider may recover.
            return null;
        }

        return $this->remember($key, $icon);
    }

    /**
     * Resolve a Blade Icons name such as "heroicon-o-user" or
     * "fas-arrow-left" through the registered Blade Icons set providers.
     * Longer prefixes win, so sets like "fa" and "fa-brands" never clash.
     */
    public function findByBladeName(string $name): ?Icon
    {
        $name = trim($name);

        if ($name === '' || str_contains($name, ':')) {
            return null;
        }

        $cacheKey = "blade:{$name}";

        if (array_key_exists($cacheKey, $this->resolved)) {
            return $this->resolved[$cacheKey];
        }

        $candidates = [];

        foreach ($this->all() as $provider) {
            if ($provider instanceof BladeIconSetProvider && str_starts_with($name, $provider->prefix().'-')) {
                $candidates[] = $provider;
            }
        }

        usort($candidates, static fn (BladeIconSetProvider $a, BladeIconSetProvider $b): int => strlen($b->prefix()) <=> strlen($a->prefix()));

        foreach ($candidates as $provider) {
            try {
                $icon = $provider->status()->isAvailable()
                    ? $provider->find(substr($name, strlen($provider->prefix()) + 1))
                    : null;
            } catch (Throwable $exception) {
                report($exception);

                continue;
            }

            if ($icon !== null) {
                return $this->remember($cacheKey, $icon);
            }
        }

        return $this->remember($cacheKey, null);
    }

    /**
     * The value to store for an icon. Blade Icons icons are stored by their
     * Blade Icons name ("heroicon-o-user") so the value works directly with
     * Filament's ->icon(), @svg() and <x-dynamic-component>; everything else
     * is stored as "provider:name". Configure with icon-hub.blade_icons.store_as.
     */
    public function storedValue(Icon $icon): string
    {
        if (
            config('icon-hub.blade_icons.store_as', 'name') === 'name'
            && $icon->source->kind === SourceKind::Blade
            && ($this->all()[$icon->provider] ?? null) instanceof BladeIconSetProvider
        ) {
            return $icon->source->value;
        }

        return $icon->key();
    }

    /**
     * Convert any accepted value (legacy "provider:name" or Blade Icons name)
     * to the configured storage format. Unknown values are returned as-is.
     */
    public function normalizeValue(string $value): string
    {
        $icon = $this->find($value);

        return $icon === null ? $value : $this->storedValue($icon);
    }

    /**
     * Search one or more providers. See {@see IconSearcher::search()}.
     *
     * @param  list<string>|null  $providers
     */
    public function search(IconQuery|string $query = '', ?array $providers = null, ?string $cursor = null): SearchPage
    {
        $query = is_string($query) ? new IconQuery($query) : $query;

        return app(IconSearcher::class)->search(
            query: $query,
            providers: array_keys($this->providers($providers)),
            cursor: $cursor,
        );
    }

    /**
     * Render an icon (or an identifier) to safe HTML. Unknown icons render
     * as an empty string.
     *
     * @param  array<array-key, mixed>  $attributes
     */
    public function html(Icon|IconId|string|null $icon, array $attributes = []): Htmlable
    {
        $icon = $icon instanceof Icon ? $icon : $this->find($icon);

        return $icon === null
            ? new HtmlString('')
            : app(IconRenderer::class)->render($icon, $attributes);
    }

    private function discover(): void
    {
        if ($this->hasDiscovered) {
            return;
        }

        $this->hasDiscovered = true;

        foreach ($this->discoverers as $discoverer) {
            try {
                foreach ($discoverer($this) as $provider) {
                    $id = $provider->id();

                    if (isset($this->forgotten[$id]) || isset($this->discovered[$id]) || ! IconId::isValidProvider($id)) {
                        continue;
                    }

                    $this->discovered[$id] = $provider;
                }
            } catch (Throwable $exception) {
                report($exception);
            }
        }
    }

    private function remember(string $key, ?Icon $icon): ?Icon
    {
        if (count($this->resolved) >= self::MAX_RESOLVED) {
            $this->resolved = [];
        }

        return $this->resolved[$key] = $icon;
    }

    private function forgetResolved(string $provider): void
    {
        // Blade-name lookups may point at any set; drop them all.
        foreach (array_keys($this->resolved) as $key) {
            if (str_starts_with($key, 'blade:')) {
                unset($this->resolved[$key]);
            }
        }

        foreach (array_keys($this->resolved) as $key) {
            if (str_starts_with($key, "{$provider}:")) {
                unset($this->resolved[$key]);
            }
        }
    }
}
