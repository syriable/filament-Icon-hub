<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\IconHub\Tests\Fixtures;

use Syriable\Filament\Plugins\IconHub\Data\Icon;
use Syriable\Filament\Plugins\IconHub\Data\IconSource;
use Syriable\Filament\Plugins\IconHub\Enums\ProviderStatus;
use Syriable\Filament\Plugins\IconHub\Providers\IndexedIconProvider;

/**
 * An in-memory provider used to test the indexed base class and the registry.
 */
final class ArrayProvider extends IndexedIconProvider
{
    /**
     * @param  list<string>  $names
     */
    public function __construct(
        private readonly string $id,
        private readonly array $names,
        private readonly ProviderStatus $status = ProviderStatus::Available,
        public int $builds = 0,
    ) {}

    public function id(): string
    {
        return $this->id;
    }

    public function label(): string
    {
        return ucfirst($this->id);
    }

    public function status(): ProviderStatus
    {
        return $this->status;
    }

    protected function buildIndex(): iterable
    {
        $this->builds++;

        foreach ($this->names as $name) {
            yield new Icon(
                provider: $this->id,
                name: $name,
                label: ucfirst(str_replace('-', ' ', $name)),
                source: IconSource::svg('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M0 0h24v24H0z"/></svg>'),
                tags: explode('-', $name),
            );
        }
    }
}
