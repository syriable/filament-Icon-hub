<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\IconHub\Actions;

use Syriable\Filament\Plugins\IconHub\Contracts\IconVisibility;
use Syriable\Filament\Plugins\IconHub\Models\HiddenIcon;

/**
 * Make a previously hidden icon available in the picker again.
 */
final readonly class RestoreIcon
{
    public function __construct(
        private IconVisibility $visibility,
    ) {}

    public function handle(string $id): bool
    {
        /** @var class-string<HiddenIcon> $model */
        $model = config('icon-hub.library.models.hidden_icon', HiddenIcon::class);
        $deleted = $model::query()->where('icon', $id)->delete() > 0;

        $this->visibility->refresh();

        return $deleted;
    }
}
