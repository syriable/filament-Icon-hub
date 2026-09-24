<?php

declare(strict_types=1);

use Syriable\Filament\Plugins\IconHub\Exceptions\InvalidIconId;
use Syriable\Filament\Plugins\IconHub\ValueObjects\IconId;

describe('IconId', function () {
    it('parses provider and name on the first colon', function () {
        $id = IconId::parse('flaticon:packs:12:arrow');

        expect($id->provider)->toBe('flaticon')
            ->and($id->name)->toBe('packs:12:arrow')
            ->and((string) $id)->toBe('flaticon:packs:12:arrow');
    });

    it('distinguishes the same name across providers', function () {
        expect(IconId::parse('heroicons:user')->equals(IconId::parse('fontawesome:user')))->toBeFalse()
            ->and(IconId::parse('custom:user')->equals(IconId::parse('custom:user')))->toBeTrue();
    });

    it('rejects invalid identifiers', function (mixed $value) {
        expect(IconId::tryParse($value))->toBeNull();
    })->with([
        'no colon' => 'heroicon-o-user',
        'empty name' => 'heroicons:',
        'empty provider' => ':user',
        'uppercase provider' => 'Heroicons:user',
        'provider with spaces' => 'hero icons:user',
        'not a string' => 42,
        'null' => null,
        'too long' => 'p:'.str_repeat('a', 300),
    ]);

    it('throws for invalid identifiers when parsing strictly', function () {
        IconId::parse('nope');
    })->throws(InvalidIconId::class);
});
