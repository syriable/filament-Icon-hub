<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\IconHub\Contracts;

use Syriable\Filament\Plugins\IconHub\Data\Icon;
use Syriable\Filament\Plugins\IconHub\Data\IconQuery;
use Syriable\Filament\Plugins\IconHub\Data\IconResults;
use Syriable\Filament\Plugins\IconHub\Data\ProviderMetadata;
use Syriable\Filament\Plugins\IconHub\Enums\ProviderStatus;

/**
 * A source of icons. Providers describe icons; they never render HTML.
 *
 * Implementations may throw from any method: the registry isolates failures
 * so that one broken provider never breaks the picker.
 */
interface IconProvider
{
    /**
     * Stable identifier used in stored icon ids, e.g. "heroicons".
     * Must match {@see \Syriable\Filament\Plugins\IconHub\ValueObjects\IconId::PROVIDER_PATTERN}.
     */
    public function id(): string;

    /**
     * Human readable name. Must be cheap: it is called on every picker render.
     */
    public function label(): string;

    /**
     * Current lifecycle state. Must be cheap: avoid network calls here.
     */
    public function status(): ProviderStatus;

    /**
     * Optional capabilities (categories, variants, ...). May perform I/O, so
     * cache anything expensive.
     */
    public function metadata(): ProviderMetadata;

    /**
     * Return one page of icons matching the query.
     */
    public function search(IconQuery $query): IconResults;

    /**
     * Resolve a single icon by its provider-local name.
     */
    public function find(string $name): ?Icon;
}
