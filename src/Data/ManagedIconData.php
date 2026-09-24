<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\IconHub\Data;

/**
 * Input for creating or updating an uploaded icon.
 */
final readonly class ManagedIconData
{
    /**
     * @param  list<string>  $tags
     */
    public function __construct(
        public string $svg,
        public ?string $label = null,
        public ?string $name = null,
        public ?string $collection = null,
        public array $tags = [],
        public bool $isEnabled = true,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $string = static fn (string $key): ?string => is_string($data[$key] ?? null) && trim($data[$key]) !== '' ? trim($data[$key]) : null;
        $tags = is_array($data['tags'] ?? null) ? $data['tags'] : [];

        return new self(
            svg: $string('svg') ?? '',
            label: $string('label'),
            name: $string('name'),
            collection: $string('collection'),
            tags: array_values(array_filter(array_map(static fn (mixed $tag): string => is_scalar($tag) ? trim((string) $tag) : '', $tags))),
            isEnabled: (bool) ($data['is_enabled'] ?? true),
        );
    }
}
