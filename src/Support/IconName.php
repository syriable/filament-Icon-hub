<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\IconHub\Support;

use Illuminate\Support\Str;

/**
 * Helpers to derive human friendly labels and search tags from icon names.
 *
 * @internal
 */
final class IconName
{
    public static function label(string $name): string
    {
        $words = trim((string) preg_replace('/[\s._\/:-]+/', ' ', $name));

        return Str::ucfirst($words);
    }

    /**
     * @return list<string>
     */
    public static function tags(string $name): array
    {
        $parts = preg_split('/[\s._\/:-]+/', mb_strtolower($name), -1, PREG_SPLIT_NO_EMPTY);

        return array_values(array_unique($parts === false ? [] : $parts));
    }
}
