<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\IconHub\Data;

/**
 * One page of a (possibly cross-provider) search, as produced by the registry.
 */
final readonly class SearchPage
{
    /**
     * @param  list<Icon>  $icons
     * @param  array<string, string>  $errors  provider id => safe error reason
     */
    public function __construct(
        public array $icons,
        public ?string $nextCursor = null,
        public array $errors = [],
    ) {}

    public function hasMore(): bool
    {
        return $this->nextCursor !== null;
    }
}
