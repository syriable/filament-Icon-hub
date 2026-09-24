<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\IconHub\Exceptions;

use InvalidArgumentException;

final class InvalidIconId extends InvalidArgumentException
{
    public static function make(string $value): self
    {
        return new self("[{$value}] is not a valid icon identifier. Expected the format \"provider:name\".");
    }
}
