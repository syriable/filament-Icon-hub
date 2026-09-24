<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\IconHub\Data;

/**
 * A normalized search request handed to a provider.
 */
final readonly class IconQuery
{
    public const int MAX_SEARCH_LENGTH = 100;

    public const int MAX_PER_PAGE = 200;

    public string $search;

    public int $page;

    public int $perPage;

    public function __construct(
        string $search = '',
        public ?string $category = null,
        public ?string $variant = null,
        int $page = 1,
        int $perPage = 60,
    ) {
        $this->search = mb_substr(trim($search), 0, self::MAX_SEARCH_LENGTH);
        $this->page = max(1, $page);
        $this->perPage = min(max(1, $perPage), self::MAX_PER_PAGE);
    }

    public function hasSearch(): bool
    {
        return $this->search !== '';
    }

    public function offset(): int
    {
        return ($this->page - 1) * $this->perPage;
    }

    public function forPage(int $page): self
    {
        return new self($this->search, $this->category, $this->variant, $page, $this->perPage);
    }

    /**
     * Deterministic representation used for cache keys.
     *
     * @return array{search: string, category: ?string, variant: ?string, page: int, per_page: int}
     */
    public function toArray(): array
    {
        return [
            'search' => mb_strtolower($this->search),
            'category' => $this->category,
            'variant' => $this->variant,
            'page' => $this->page,
            'per_page' => $this->perPage,
        ];
    }
}
