<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\IconHub\Tests\Fixtures;

use RuntimeException;
use Syriable\Filament\Plugins\IconHub\Contracts\IconProvider;
use Syriable\Filament\Plugins\IconHub\Data\Icon;
use Syriable\Filament\Plugins\IconHub\Data\IconQuery;
use Syriable\Filament\Plugins\IconHub\Data\IconResults;
use Syriable\Filament\Plugins\IconHub\Data\ProviderMetadata;
use Syriable\Filament\Plugins\IconHub\Enums\ProviderStatus;

final class BrokenProvider implements IconProvider
{
    public function id(): string
    {
        return 'broken';
    }

    public function label(): string
    {
        return 'Broken';
    }

    public function status(): ProviderStatus
    {
        return ProviderStatus::Available;
    }

    public function metadata(): ProviderMetadata
    {
        throw new RuntimeException('metadata exploded');
    }

    public function search(IconQuery $query): IconResults
    {
        throw new RuntimeException('search exploded');
    }

    public function find(string $name): ?Icon
    {
        throw new RuntimeException('find exploded');
    }
}
