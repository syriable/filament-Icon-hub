<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\IconHub\Data;

/**
 * Optional capabilities a provider exposes to the picker. Empty categories or
 * variants simply hide the matching filter in the UI.
 */
final readonly class ProviderMetadata
{
    /**
     * @param  array<string, string>  $categories  value => label
     * @param  array<string, string>  $variants  value => label
     */
    public function __construct(
        public array $categories = [],
        public array $variants = [],
        public bool $remote = false,
        public ?int $total = null,
    ) {}

    public function supportsCategories(): bool
    {
        return $this->categories !== [];
    }

    public function supportsVariants(): bool
    {
        return $this->variants !== [];
    }

    /**
     * @return array{categories: array<string, string>, variants: array<string, string>, remote: bool, total: ?int}
     */
    public function toArray(): array
    {
        return [
            'categories' => $this->categories,
            'variants' => $this->variants,
            'remote' => $this->remote,
            'total' => $this->total,
        ];
    }

    /**
     * @param  array{categories: array<string, string>, variants: array<string, string>, remote: bool, total: ?int}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self($data['categories'], $data['variants'], $data['remote'], $data['total']);
    }
}
