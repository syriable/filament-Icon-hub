<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\IconHub\Commands;

use Illuminate\Console\Command;
use Syriable\Filament\Plugins\IconHub\Cache\IconCache;
use Syriable\Filament\Plugins\IconHub\Registry\IconRegistry;

final class ClearIconCacheCommand extends Command
{
    protected $signature = 'icon-hub:clear {provider? : Only clear the cache of this provider}';

    protected $description = 'Clear cached icon indexes, search results and icon lookups';

    public function handle(IconCache $cache, IconRegistry $registry): int
    {
        $provider = $this->argument('provider');

        if (is_string($provider)) {
            if (! $registry->has($provider)) {
                $this->components->error("No icon provider is registered with the id [{$provider}].");

                return self::FAILURE;
            }

            $cache->flush($provider);
            $this->components->info("Icon cache cleared for [{$provider}].");

            return self::SUCCESS;
        }

        $cache->flush();
        $this->components->info('Icon cache cleared for all providers.');

        return self::SUCCESS;
    }
}
