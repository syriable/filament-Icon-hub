<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\IconHub\Cache;

use Closure;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Cache\Repository;

/**
 * Thin wrapper around a Laravel cache store with deterministic keys and
 * generation-based invalidation (works on every store, tags not required).
 *
 * Key layout: {prefix}:{global generation}:{provider}:{provider generation}:{type}:{hash(params)}
 */
final class IconCache
{
    public const string TYPE_INDEX = 'index';

    public const string TYPE_SEARCH = 'search';

    public const string TYPE_ICON = 'icon';

    public const string TYPE_METADATA = 'metadata';

    public const string TYPE_SVG = 'svg';

    /**
     * @param  array<string, int|null>  $ttl  seconds per type; null caches forever
     */
    public function __construct(
        private readonly CacheFactory $cache,
        private readonly bool $enabled = true,
        private readonly ?string $store = null,
        private readonly string $prefix = 'icon-hub',
        private readonly array $ttl = [],
        private readonly int $defaultTtl = 3600,
    ) {}

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * @template TValue
     *
     * @param  array<mixed>  $params
     * @param  Closure(): TValue  $callback
     * @return TValue
     */
    public function remember(string $provider, string $type, array $params, Closure $callback): mixed
    {
        if (! $this->enabled) {
            return $callback();
        }

        $key = $this->key($provider, $type, $params);
        $ttl = $this->ttl($type);

        return $ttl === null
            ? $this->repository()->rememberForever($key, $callback)
            : $this->repository()->remember($key, $ttl, $callback);
    }

    /**
     * @param  array<mixed>  $params
     */
    public function get(string $provider, string $type, array $params): mixed
    {
        return $this->enabled ? $this->repository()->get($this->key($provider, $type, $params)) : null;
    }

    /**
     * @param  array<mixed>  $params
     */
    public function put(string $provider, string $type, array $params, mixed $value, ?int $seconds = null): void
    {
        if (! $this->enabled) {
            return;
        }

        $ttl = $seconds ?? $this->ttl($type);
        $key = $this->key($provider, $type, $params);

        $ttl === null
            ? $this->repository()->forever($key, $value)
            : $this->repository()->put($key, $value, $ttl);
    }

    /**
     * Invalidate every entry of one provider, or of all providers when null.
     */
    public function flush(?string $provider = null): void
    {
        $generationKey = $this->generationKey($provider);

        $this->repository()->forever($generationKey, $this->generation($generationKey) + 1);
    }

    /**
     * @param  array<mixed>  $params
     */
    public function key(string $provider, string $type, array $params): string
    {
        $global = $this->generation($this->generationKey(null));
        $local = $this->generation($this->generationKey($provider));
        $hash = sha1((string) json_encode($this->normalize($params)));

        return "{$this->prefix}:{$global}:{$provider}:{$local}:{$type}:{$hash}";
    }

    private function ttl(string $type): ?int
    {
        return array_key_exists($type, $this->ttl) ? $this->ttl[$type] : $this->defaultTtl;
    }

    private function generationKey(?string $provider): string
    {
        return $provider === null
            ? "{$this->prefix}:generation"
            : "{$this->prefix}:generation:{$provider}";
    }

    private function generation(string $key): int
    {
        $value = $this->repository()->get($key);

        return is_int($value) ? $value : 0;
    }

    /**
     * Sort associative keys recursively so equivalent params hash identically.
     *
     * @param  array<mixed>  $params
     * @return array<mixed>
     */
    private function normalize(array $params): array
    {
        if (! array_is_list($params)) {
            ksort($params);
        }

        return array_map(fn (mixed $value): mixed => is_array($value) ? $this->normalize($value) : $value, $params);
    }

    private function repository(): Repository
    {
        return $this->cache->store($this->store);
    }
}
