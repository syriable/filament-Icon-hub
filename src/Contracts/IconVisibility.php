<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\IconHub\Contracts;

/**
 * Decides which icons are hidden (ignored) in the picker. Hidden icons still
 * render when already stored, they just can no longer be picked.
 */
interface IconVisibility
{
    public function isHidden(string $iconId): bool;

    /**
     * @return list<string>
     */
    public function hiddenIds(): array;

    /**
     * Forget any in-memory state, e.g. after icons were hidden or restored.
     */
    public function refresh(): void;
}
