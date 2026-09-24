<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\IconHub;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Syriable\Filament\Plugins\IconHub\Filament\Resources\HiddenIcons\HiddenIconResource;
use Syriable\Filament\Plugins\IconHub\Filament\Resources\ManagedIcons\ManagedIconResource;

/**
 * Only required for the optional library management screens. The picker
 * field, table column and infolist entry work without registering it.
 */
final class IconHubPlugin implements Plugin
{
    private bool $managesLibrary = false;

    public static function make(): self
    {
        return app(self::class);
    }

    public static function get(): self
    {
        /** @var self $plugin */
        $plugin = filament(app(self::class)->getId());

        return $plugin;
    }

    public function getId(): string
    {
        return 'icon-hub';
    }

    /**
     * Register the "Icon library" (uploaded icons) and "Hidden icons"
     * resources on this panel. Requires `icon-hub.library.enabled`.
     */
    public function manageLibrary(bool $condition = true): self
    {
        $this->managesLibrary = $condition;

        return $this;
    }

    public function managesLibrary(): bool
    {
        return $this->managesLibrary && (bool) config('icon-hub.library.enabled', false);
    }

    public function register(Panel $panel): void
    {
        if ($this->managesLibrary()) {
            $panel->resources([
                ManagedIconResource::class,
                HiddenIconResource::class,
            ]);
        }
    }

    public function boot(Panel $panel): void {}
}
