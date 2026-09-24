<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\IconHub\Actions;

use Illuminate\Support\Facades\Date;
use Syriable\Filament\Plugins\IconHub\Contracts\IconVisibility;
use Syriable\Filament\Plugins\IconHub\Models\HiddenIcon;
use Syriable\Filament\Plugins\IconHub\ValueObjects\IconId;

/**
 * Hide (ignore) icons from the picker without touching their source.
 */
final readonly class HideIcons
{
    public function __construct(
        private IconVisibility $visibility,
    ) {}

    /**
     * @param  iterable<string>  $ids
     * @return int number of valid identifiers processed
     */
    public function handle(iterable $ids): int
    {
        $now = Date::now();
        $rows = [];

        foreach ($ids as $id) {
            $iconId = IconId::tryParse($id);

            if ($iconId !== null) {
                $rows[(string) $iconId] = ['icon' => (string) $iconId, 'created_at' => $now, 'updated_at' => $now];
            }
        }

        if ($rows === []) {
            return 0;
        }

        /** @var class-string<HiddenIcon> $model */
        $model = config('icon-hub.library.models.hidden_icon', HiddenIcon::class);
        $model::query()->upsert(array_values($rows), ['icon'], ['updated_at']);

        $this->visibility->refresh();

        return count($rows);
    }
}
