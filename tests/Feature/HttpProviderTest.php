<?php

declare(strict_types=1);

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Syriable\Filament\Plugins\IconHub\Data\IconQuery;
use Syriable\Filament\Plugins\IconHub\Enums\ProviderStatus;
use Syriable\Filament\Plugins\IconHub\Exceptions\ProviderException;
use Syriable\Filament\Plugins\IconHub\Facades\IconHub;
use Syriable\Filament\Plugins\IconHub\Tests\Fixtures\FakeApiProvider;

function apiIcons(int $count = 2, bool $hasMore = false): array
{
    return [
        'data' => array_map(fn (int $i) => [
            'id' => "icon-{$i}",
            'name' => "Icon {$i}",
            'preview_url' => "https://cdn.icons.test/{$i}.png",
            'tags' => ['demo'],
        ], range(1, $count)),
        'meta' => ['has_more' => $hasMore, 'total' => $count],
    ];
}

beforeEach(function () {
    IconHub::register(new FakeApiProvider);
});

describe('HttpIconProvider', function () {
    it('maps a successful search response', function () {
        Http::fake(['icons.test/*' => Http::response(apiIcons(2, hasMore: true))]);

        $results = IconHub::provider('remote')->search(new IconQuery('demo'));

        expect($results->icons)->toHaveCount(2)
            ->and($results->icons[0]->key())->toBe('remote:icon-1')
            ->and($results->hasMore)->toBeTrue();

        Http::assertSent(fn (Request $request) => $request->hasHeader('Authorization', 'Bearer secret-key')
            && str_contains($request->url(), 'q=demo'));
    });

    it('caches search results per query', function () {
        Http::fake(['icons.test/*' => Http::response(apiIcons())]);
        $provider = IconHub::provider('remote');

        $provider->search(new IconQuery('demo'));
        $provider->search(new IconQuery('demo'));
        $provider->search(new IconQuery('other'));

        Http::assertSentCount(2);
    });

    it('primes the icon cache from search results so rendering does not call the api', function () {
        Http::fake(['icons.test/*' => Http::response(apiIcons())]);

        IconHub::provider('remote')->search(new IconQuery('demo'));
        $html = IconHub::html('remote:icon-2')->toHtml();

        expect($html)->toContain('src="https://cdn.icons.test/2.png"');
        Http::assertSentCount(1);
    });

    it('looks up unknown icons once and caches misses', function () {
        Http::fake(['icons.test/api/icons/*' => Http::response(['error' => 'missing'], 200)]);
        $provider = IconHub::provider('remote');

        expect($provider->find('nope'))->toBeNull()
            ->and($provider->find('nope'))->toBeNull();

        Http::assertSentCount(1);
    });

    it('maps failures to safe provider exceptions', function (Closure $response, string $reason) {
        Http::fake(['icons.test/*' => $response]);

        try {
            IconHub::provider('remote')->search(new IconQuery('demo'));
            $this->fail('Expected a provider exception.');
        } catch (ProviderException $exception) {
            expect($exception->reason)->toBe($reason)
                ->and($exception->getMessage())->not->toContain('secret-key');
        }
    })->with([
        'timeout' => [fn () => fn () => throw new ConnectionException('cURL error 28: timed out'), ProviderException::TIMEOUT],
        'server error' => [fn () => Http::response('oops', 500), ProviderException::UNAVAILABLE],
        'unauthorized' => [fn () => Http::response(['message' => 'bad key'], 401), ProviderException::UNAUTHORIZED],
        'forbidden' => [fn () => Http::response([], 403), ProviderException::UNAUTHORIZED],
        'rate limited' => [fn () => Http::response([], 429, ['Retry-After' => '30']), ProviderException::RATE_LIMITED],
        'malformed json' => [fn () => Http::response('{not json', 200), ProviderException::INVALID_RESPONSE],
        'scalar json' => [fn () => Http::response('"string"', 200), ProviderException::INVALID_RESPONSE],
        'missing data' => [fn () => Http::response(['unexpected' => true], 200), ProviderException::INVALID_RESPONSE],
    ]);

    it('stops calling the api during a rate limit cool-down', function () {
        Http::fake(['icons.test/*' => Http::response([], 429, ['Retry-After' => '60'])]);
        $provider = IconHub::provider('remote');

        expect(fn () => $provider->search(new IconQuery('a')))->toThrow(ProviderException::class)
            ->and(fn () => $provider->search(new IconQuery('b')))->toThrow(ProviderException::class);

        Http::assertSentCount(1);
    });

    it('is reported as unconfigured without credentials and never calls the api', function () {
        Http::fake();
        IconHub::register(new FakeApiProvider(apiKey: null));

        $page = IconHub::search('demo', ['remote']);

        expect(IconHub::status('remote'))->toBe(ProviderStatus::Unconfigured)
            ->and($page->errors)->toBe(['remote' => 'unconfigured'])
            ->and(IconHub::find('remote:icon-1'))->toBeNull();

        Http::assertNothingSent();
    });

    it('keeps other providers working when the api is down', function () {
        Http::fake(['icons.test/*' => fn () => throw new ConnectionException('down')]);

        $page = IconHub::search('user', ['remote', 'heroicons']);

        expect($page->errors)->toBe(['remote' => 'timeout'])
            ->and($page->icons)->not->toBeEmpty();
    });
});

describe('Malicious API responses', function () {
    it('sanitizes svg markup returned by an api', function () {
        Http::fake(['icons.test/*' => Http::response(['data' => [[
            'id' => 'evil',
            'name' => 'Evil',
            'svg' => '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"><script>alert(1)</script><path d="M0 0"/></svg>',
        ]]])]);

        IconHub::provider('remote')->search(new IconQuery);
        $html = IconHub::html('remote:evil')->toHtml();

        expect($html)->toContain('<path')->not->toContain('script')->not->toContain('onload');
    });

    it('refuses unsafe preview urls', function (string $url) {
        Http::fake(['icons.test/*' => Http::response(['data' => [['id' => 'x', 'name' => 'X', 'preview_url' => $url]]])]);

        IconHub::provider('remote')->search(new IconQuery);

        expect(IconHub::html('remote:x')->toHtml())->toBe('');
    })->with([
        'javascript' => 'javascript:alert(1)',
        'data' => 'data:image/svg+xml;base64,PHN2Zz4=',
        'http' => 'http://cdn.icons.test/x.png',
        'credentials' => 'https://user:pass@cdn.icons.test/x.png',
        'relative' => '/x.png',
    ]);

    it('escapes labels in image alt text', function () {
        Http::fake(['icons.test/*' => Http::response(['data' => [['id' => 'x', 'name' => '"><script>alert(1)</script>', 'preview_url' => 'https://cdn.icons.test/x.png']]])]);

        IconHub::provider('remote')->search(new IconQuery);

        expect(IconHub::html('remote:x', ['aria-label' => 'x'])->toHtml())->not->toContain('<script>');
    });
});
