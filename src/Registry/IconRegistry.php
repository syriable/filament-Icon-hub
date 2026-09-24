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
use Syriable\Filament\Plugins\IconHub\Exceptions\ProviderNotFound;
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
     * Resolve an icon from its "provider:name" identifier. Never throws:
     * invalid ids, unknown providers and failing providers return null.
     */
    public function find(IconId|string|null $id): ?Icon
    {
        $iconId = $id instanceof IconId ? $id : IconId::tryParse($id);

        if ($iconId === null) {
            return null;
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

        if (count($this->resolved) >= self::MAX_RESOLVED) {
            $this->resolved = [];
        }

        return $this->resolved[$key] = $icon;
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

    private function forgetResolved(string $provider): void
    {
        foreach (array_keys($this->resolved) as $key) {
            if (str_starts_with($key, "{$provider}:")) {
                unset($this->resolved[$key]);
            }
        }
    }
}
