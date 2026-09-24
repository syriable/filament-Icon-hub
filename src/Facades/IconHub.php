<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\IconHub\Facades;

use Illuminate\Support\Facades\Facade;
use Syriable\Filament\Plugins\IconHub\Registry\IconRegistry;

/**
 * @method static IconRegistry register(\Syriable\Filament\Plugins\IconHub\Contracts\IconProvider|class-string<\Syriable\Filament\Plugins\IconHub\Contracts\IconProvider> $provider)
 * @method static IconRegistry forget(string $id)
 * @method static IconRegistry discoverUsing(\Closure $discoverer)
 * @method static IconRegistry rediscover()
 * @method static bool has(string $id)
 * @method static \Syriable\Filament\Plugins\IconHub\Contracts\IconProvider provider(string $id)
 * @method static array<string, \Syriable\Filament\Plugins\IconHub\Contracts\IconProvider> all()
 * @method static array<string, \Syriable\Filament\Plugins\IconHub\Contracts\IconProvider> providers(list<string>|null $only = null)
 * @method static \Syriable\Filament\Plugins\IconHub\Enums\ProviderStatus status(string $id)
 * @method static \Syriable\Filament\Plugins\IconHub\Data\ProviderMetadata metadata(string $id)
 * @method static \Syriable\Filament\Plugins\IconHub\Data\Icon|null find(\Syriable\Filament\Plugins\IconHub\ValueObjects\IconId|string|null $id)
 * @method static \Syriable\Filament\Plugins\IconHub\Data\SearchPage search(\Syriable\Filament\Plugins\IconHub\Data\IconQuery|string $query = '', list<string>|null $providers = null, string|null $cursor = null)
 * @method static \Illuminate\Contracts\Support\Htmlable html(\Syriable\Filament\Plugins\IconHub\Data\Icon|\Syriable\Filament\Plugins\IconHub\ValueObjects\IconId|string|null $icon, array<array-key, mixed> $attributes = [])
 *
 * @see IconRegistry
 */
final class IconHub extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return IconRegistry::class;
    }
}
