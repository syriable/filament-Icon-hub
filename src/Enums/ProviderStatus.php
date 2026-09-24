<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\IconHub\Enums;

enum ProviderStatus: string
{
    /** The provider is ready to search and resolve icons. */
    case Available = 'available';

    /** The provider is missing required configuration (e.g. an API key). */
    case Unconfigured = 'unconfigured';

    /** The provider is configured but currently cannot be used. */
    case Unavailable = 'unavailable';

    public function isAvailable(): bool
    {
        return $this === self::Available;
    }
}
