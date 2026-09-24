<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\IconHub\Actions;

use Syriable\Filament\Plugins\IconHub\Contracts\IconVisibility;
use Syriable\Filament\Plugins\IconHub\Models\HiddenIcon;
use Syriable\Filament\Plugins\IconHub\Registry\IconRegistry;

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
        // Hidden rows use "provider:name"; accept Blade Icons names too.
        $key = app(IconRegistry::class)->find($id)?->key() ?? $id;
        $deleted = $model::query()->where('icon', $key)->delete() > 0;

        $this->visibility->refresh();

        return $deleted;
    }
}
