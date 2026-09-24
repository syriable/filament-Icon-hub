<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\IconHub\Data;

/**
 * One page of icons returned by a provider.
 *
 * @phpstan-import-type IconArray from Icon
 */
final readonly class IconResults
{
    /**
     * @param  list<Icon>  $icons
     */
    public function __construct(
        public array $icons,
        public bool $hasMore = false,
        public ?int $total = null,
    ) {}

    public static function empty(): self
    {
        return new self([]);
    }

    public function isEmpty(): bool
    {
        return $this->icons === [];
    }

    /**
     * @return array{icons: list<IconArray>, has_more: bool, total: ?int}
     */
    public function toArray(): array
    {
        return [
            'icons' => array_map(static fn (Icon $icon): array => $icon->toArray(), $this->icons),
            'has_more' => $this->hasMore,
            'total' => $this->total,
        ];
    }

    /**
     * @param  array{icons: list<IconArray>, has_more: bool, total: ?int}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            icons: array_map(static fn (array $icon): Icon => Icon::fromArray($icon), $data['icons']),
            hasMore: $data['has_more'],
            total: $data['total'],
        );
    }
}
