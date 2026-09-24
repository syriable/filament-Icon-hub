<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\IconHub\ValueObjects;

use Stringable;
use Syriable\Filament\Plugins\IconHub\Exceptions\InvalidIconId;

/**
 * The stable, namespaced identity of an icon: "{provider}:{name}".
 *
 * The provider part identifies the registered provider; the name is owned
 * by that provider and may itself contain "/", "." or ":" characters.
 */
final readonly class IconId implements Stringable
{
    public const string PROVIDER_PATTERN = '/^[a-z0-9][a-z0-9._-]*$/';

    public const int MAX_LENGTH = 255;

    public function __construct(
        public string $provider,
        public string $name,
    ) {
        if (! self::isValidProvider($provider) || $name === '' || strlen((string) $this) > self::MAX_LENGTH) {
            throw InvalidIconId::make("{$provider}:{$name}");
        }
    }

    public static function parse(string $value): self
    {
        $parts = explode(':', trim($value), 2);

        if (count($parts) !== 2) {
            throw InvalidIconId::make($value);
        }

        return new self($parts[0], $parts[1]);
    }

    public static function tryParse(mixed $value): ?self
    {
        if (! is_string($value)) {
            return null;
        }

        try {
            return self::parse($value);
        } catch (InvalidIconId) {
            return null;
        }
    }

    public static function isValidProvider(string $provider): bool
    {
        return preg_match(self::PROVIDER_PATTERN, $provider) === 1;
    }

    public function equals(self $other): bool
    {
        return $this->provider === $other->provider && $this->name === $other->name;
    }

    public function __toString(): string
    {
        return "{$this->provider}:{$this->name}";
    }
}
