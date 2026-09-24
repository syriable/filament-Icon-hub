<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\IconHub\Tests\Fixtures;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Syriable\Filament\Plugins\IconHub\Data\Icon;
use Syriable\Filament\Plugins\IconHub\Data\IconQuery;
use Syriable\Filament\Plugins\IconHub\Data\IconResults;
use Syriable\Filament\Plugins\IconHub\Data\IconSource;
use Syriable\Filament\Plugins\IconHub\Providers\HttpIconProvider;

final class FakeApiProvider extends HttpIconProvider
{
    public function __construct(
        private readonly ?string $apiKey = 'secret-key',
        private readonly string $id = 'remote',
    ) {}

    public function id(): string
    {
        return $this->id;
    }

    public function label(): string
    {
        return 'Remote API';
    }

    protected function isConfigured(): bool
    {
        return $this->apiKey !== null;
    }

    protected function http(): PendingRequest
    {
        return parent::http()->withToken((string) $this->apiKey)->baseUrl('https://icons.test/api');
    }

    protected function searchRequest(PendingRequest $http, IconQuery $query): Response
    {
        return $http->get('/search', ['q' => $query->search, 'page' => $query->page, 'limit' => $query->perPage]);
    }

    protected function mapSearchResponse(array $data, IconQuery $query): IconResults
    {
        return new IconResults(
            icons: array_map(fn (array $item): Icon => $this->toIcon($item), $data['data']),
            hasMore: (bool) ($data['meta']['has_more'] ?? false),
            total: $data['meta']['total'] ?? null,
        );
    }

    protected function findRequest(PendingRequest $http, string $name): ?Response
    {
        return $http->get("/icons/{$name}");
    }

    protected function mapIconResponse(array $data, string $name): ?Icon
    {
        return isset($data['data']) ? $this->toIcon($data['data']) : null;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function toIcon(array $item): Icon
    {
        return new Icon(
            provider: $this->id,
            name: (string) $item['id'],
            label: (string) $item['name'],
            source: isset($item['svg']) ? IconSource::svg((string) $item['svg']) : IconSource::url((string) $item['preview_url']),
            tags: $item['tags'] ?? [],
        );
    }
}
