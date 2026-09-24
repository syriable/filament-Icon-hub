<?php

declare(strict_types=1);

use Syriable\Filament\Plugins\IconHub\Data\Icon;
use Syriable\Filament\Plugins\IconHub\Data\IconQuery;
use Syriable\Filament\Plugins\IconHub\Data\IconResults;
use Syriable\Filament\Plugins\IconHub\Data\IconSource;
use Syriable\Filament\Plugins\IconHub\Data\ManagedIconData;
use Syriable\Filament\Plugins\IconHub\Enums\SourceKind;

describe('Icon', function () {
    it('round-trips through arrays for caching', function () {
        $icon = new Icon(
            provider: 'heroicons',
            name: 'o-user',
            label: 'User',
            source: IconSource::blade('heroicon-o-user'),
            category: 'people',
            variant: 'outline',
            tags: ['user', 'person'],
            metadata: ['author' => 'Tailwind'],
        );

        $copy = Icon::fromArray($icon->toArray());

        expect($copy)->toEqual($icon)
            ->and($copy->key())->toBe('heroicons:o-user')
            ->and($copy->source->kind)->toBe(SourceKind::Blade);
    });

    it('round-trips results', function () {
        $results = new IconResults([new Icon('p', 'a', 'A', IconSource::url('https://x.test/a.svg'))], hasMore: true, total: 10);

        expect(IconResults::fromArray($results->toArray()))->toEqual($results);
    });
});

describe('IconQuery', function () {
    it('normalizes and clamps input', function () {
        $query = new IconQuery(search: '  '.str_repeat('a', 500).'  ', page: -3, perPage: 5000);

        expect(mb_strlen($query->search))->toBe(IconQuery::MAX_SEARCH_LENGTH)
            ->and($query->page)->toBe(1)
            ->and($query->perPage)->toBe(IconQuery::MAX_PER_PAGE);
    });

    it('produces distinct cache representations for distinct searches', function () {
        expect((new IconQuery('user'))->toArray())->not->toBe((new IconQuery('home'))->toArray())
            ->and((new IconQuery('User'))->toArray())->toBe((new IconQuery('user'))->toArray())
            ->and((new IconQuery('user', page: 2))->toArray())->not->toBe((new IconQuery('user'))->toArray());
    });

    it('computes offsets', function () {
        expect((new IconQuery(page: 3, perPage: 20))->offset())->toBe(40);
    });
});

describe('ManagedIconData', function () {
    it('builds from loose form data', function () {
        $data = ManagedIconData::fromArray(['svg' => '<svg/>', 'label' => ' Logo ', 'collection' => '', 'tags' => ['a', ' ', 'b', ['x']]]);

        expect($data->label)->toBe('Logo')
            ->and($data->collection)->toBeNull()
            ->and($data->tags)->toBe(['a', 'b'])
            ->and($data->isEnabled)->toBeTrue();
    });
});
