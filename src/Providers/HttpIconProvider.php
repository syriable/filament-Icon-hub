<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\IconHub\Providers;

use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Syriable\Filament\Plugins\IconHub\Cache\IconCache;
use Syriable\Filament\Plugins\IconHub\Contracts\IconProvider;
use Syriable\Filament\Plugins\IconHub\Data\Icon;
use Syriable\Filament\Plugins\IconHub\Data\IconQuery;
use Syriable\Filament\Plugins\IconHub\Data\IconResults;
use Syriable\Filament\Plugins\IconHub\Data\ProviderMetadata;
use Syriable\Filament\Plugins\IconHub\Enums\ProviderStatus;
use Syriable\Filament\Plugins\IconHub\Exceptions\ProviderException;
use Throwable;

/**
 * Base class for icon providers backed by a remote HTTP API (Flaticon, a
 * Font Awesome API, an internal design-system service, ...).
 *
 * Subclasses describe the request and map the response. This class handles
 * timeouts, HTTP errors, malformed JSON, rate-limit cool-downs and caching:
 *
 *   Picker -> Registry -> Provider -> IconCache -> Remote API
 *
 * Search results prime the icon cache, so rendering a previously picked icon
 * normally never hits the API.
 *
 * @phpstan-import-type IconArray from Icon
 */
abstract class HttpIconProvider implements IconProvider
{
    /** Request timeout in seconds. */
    protected int $timeout = 5;

    /** Connect timeout in seconds. */
    protected int $connectTimeout = 3;

    /** Fallback cool-down (seconds) after HTTP 429 without a Retry-After header. */
    protected int $rateLimitCooldown = 60;

    /**
     * Send the search request for the given query.
     */
    abstract protected function searchRequest(PendingRequest $http, IconQuery $query): Response;

    /**
     * Map a decoded search response to normalized icons.
     *
     * @param  array<mixed>  $data
     */
    abstract protected function mapSearchResponse(array $data, IconQuery $query): IconResults;

    /**
     * Send a request resolving a single icon. Return null when the API has
     * no such endpoint; lookups then rely on icons cached from searches.
     */
    protected function findRequest(PendingRequest $http, string $name): ?Response
    {
        return null;
    }

    /**
     * Map a decoded single-icon response.
     *
     * @param  array<mixed>  $data
     */
    protected function mapIconResponse(array $data, string $name): ?Icon
    {
        return null;
    }

    /**
     * Whether required configuration (API keys, base URLs, ...) is present.
     */
    protected function isConfigured(): bool
    {
        return true;
    }

    /**
     * Base HTTP client. Override to add authentication, base URL or headers.
     * Credentials stay on the server: they are never part of an Icon.
     */
    protected function http(): PendingRequest
    {
        return Http::acceptJson()
            ->timeout($this->timeout)
            ->connectTimeout($this->connectTimeout);
    }

    public function status(): ProviderStatus
    {
        return $this->isConfigured() ? ProviderStatus::Available : ProviderStatus::Unconfigured;
    }

    public function metadata(): ProviderMetadata
    {
        return new ProviderMetadata(remote: true);
    }

    public function search(IconQuery $query): IconResults
    {
        $this->ensureConfigured();

        /** @var array{icons: list<IconArray>, has_more: bool, total: ?int} $cached */
        $cached = $this->cache()->remember(
            $this->id(),
            IconCache::TYPE_SEARCH,
            $query->toArray(),
            function () use ($query): array {
                $data = $this->send(fn (PendingRequest $http): Response => $this->searchRequest($http, $query));
                $results = $this->map(fn (): IconResults => $this->mapSearchResponse($data, $query));

                foreach ($results->icons as $icon) {
                    $this->cache()->put($this->id(), IconCache::TYPE_ICON, [$icon->name], $icon->toArray());
                }

                return $results->toArray();
            },
        );

        return IconResults::fromArray($cached);
    }

    public function find(string $name): ?Icon
    {
        $cached = $this->cache()->get($this->id(), IconCache::TYPE_ICON, [$name]);

        if (is_array($cached)) {
            /** @var IconArray $cached */
            return Icon::fromArray($cached);
        }

        if ($cached === false || ! $this->isConfigured()) {
            return null;
        }

        $data = $this->send(fn (PendingRequest $http): ?Response => $this->findRequest($http, $name));

        if ($data === null) {
            return null;
        }

        $icon = $this->map(fn (): ?Icon => $this->mapIconResponse($data, $name));

        // Cache misses too, so unknown ids never cause repeated API calls.
        $this->cache()->put($this->id(), IconCache::TYPE_ICON, [$name], $icon?->toArray() ?? false);

        return $icon;
    }

    protected function cache(): IconCache
    {
        return app(IconCache::class);
    }

    /**
     * @template TResponse of Response|null
     *
     * @param  Closure(PendingRequest): TResponse  $request
     * @return (TResponse is null ? array<mixed>|null : array<mixed>)
     */
    private function send(Closure $request): ?array
    {
        if ($this->cache()->get($this->id(), 'cooldown', []) === true) {
            throw ProviderException::rateLimited($this->id());
        }

        try {
            $response = $request($this->http());
        } catch (ConnectionException $exception) {
            throw ProviderException::timeout($this->id(), $exception);
        }

        if ($response === null) {
            return null;
        }

        if ($response->status() === 429) {
            $retryAfter = (int) $response->header('Retry-After');
            $this->cache()->put($this->id(), 'cooldown', [], true, $retryAfter > 0 ? min($retryAfter, 3600) : $this->rateLimitCooldown);

            throw ProviderException::rateLimited($this->id());
        }

        if (in_array($response->status(), [401, 403], true)) {
            throw ProviderException::unauthorized($this->id(), $response->status());
        }

        if (! $response->successful()) {
            throw ProviderException::unavailable($this->id(), "HTTP {$response->status()}.");
        }

        try {
            $data = json_decode($response->body(), true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable $exception) {
            throw ProviderException::invalidResponse($this->id(), 'Malformed JSON.', $exception);
        }

        if (! is_array($data)) {
            throw ProviderException::invalidResponse($this->id(), 'Expected a JSON object or array.');
        }

        return $data;
    }

    /**
     * Mapping code works on untrusted data; any error becomes an invalid
     * response instead of a crash.
     *
     * @template TResult
     *
     * @param  Closure(): TResult  $mapper
     * @return TResult
     */
    private function map(Closure $mapper): mixed
    {
        try {
            return $mapper();
        } catch (ProviderException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw ProviderException::invalidResponse($this->id(), $exception->getMessage(), $exception);
        }
    }

    private function ensureConfigured(): void
    {
        if (! $this->isConfigured()) {
            throw ProviderException::unconfigured($this->id());
        }
    }
}
