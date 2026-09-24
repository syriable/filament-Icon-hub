<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\IconHub\Exceptions;

use RuntimeException;

final class ProviderNotFound extends RuntimeException
{
    public static function make(string $id): self
    {
        return new self("No icon provider is registered with the id [{$id}].");
    }
}
