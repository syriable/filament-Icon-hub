<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\IconHub\Data;

use Syriable\Filament\Plugins\IconHub\ValueObjects\IconId;

/**
 * A normalized icon. The picker, columns and entries only ever work with this
 * object, regardless of which provider produced it.
 *
 * @phpstan-type IconArray array{
 *     provider: string,
 *     name: string,
 *     label: string,
 *     source: array{kind: string, value: string},
 *     category: ?string,
 *     variant: ?string,
 *     tags: list<string>,
 *     metadata: array<string, mixed>,
 * }
 */
final readonly class Icon
{
    /**
     * @param  list<string>  $tags
     * @param  array<string, mixed>  $metadata  Provider-specific extras. Never sent to the browser.
     */
    public function __construct(
        public string $provider,
        public string $name,
        public string $label,
        public IconSource $source,
        public ?string $category = null,
        public ?string $variant = null,
        public array $tags = [],
        public array $metadata = [],
    ) {}

    public function id(): IconId
    {
        return new IconId($this->provider, $this->name);
    }

    public function key(): string
    {
        return (string) $this->id();
    }

    /**
     * @return IconArray
     */
    public function toArray(): array
    {
        return [
            'provider' => $this->provider,
            'name' => $this->name,
            'label' => $this->label,
            'source' => $this->source->toArray(),
            'category' => $this->category,
            'variant' => $this->variant,
            'tags' => $this->tags,
            'metadata' => $this->metadata,
        ];
    }

    /**
     * @param  IconArray  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            provider: $data['provider'],
            name: $data['name'],
            label: $data['label'],
            source: IconSource::fromArray($data['source']),
            category: $data['category'] ?? null,
            variant: $data['variant'] ?? null,
            tags: $data['tags'] ?? [],
            metadata: $data['metadata'] ?? [],
        );
    }
}
