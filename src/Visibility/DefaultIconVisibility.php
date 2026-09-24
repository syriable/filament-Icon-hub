<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\IconHub\Visibility;

use Syriable\Filament\Plugins\IconHub\Contracts\IconVisibility;
use Syriable\Filament\Plugins\IconHub\Models\HiddenIcon;
use Syriable\Filament\Plugins\IconHub\Registry\IconRegistry;
use Throwable;

/**
 * Hidden icons come from the "hidden" config list and, when the library is
 * enabled, from the hidden icons table. Loaded once per instance.
 */
final class DefaultIconVisibility implements IconVisibility
{
    /** @var array<string, true>|null */
    private ?array $hidden = null;

    /**
     * @param  list<string>  $configured
     * @param  class-string<HiddenIcon>|null  $model
     */
    public function __construct(
        private readonly array $configured = [],
        private readonly ?string $model = null,
    ) {}

    public function isHidden(string $iconId): bool
    {
        return isset($this->load()[$iconId]);
    }

    public function hiddenIds(): array
    {
        return array_keys($this->load());
    }

    public function refresh(): void
    {
        $this->hidden = null;
    }

    /**
     * @return array<string, true>
     */
    private function load(): array
    {
        if ($this->hidden !== null) {
            return $this->hidden;
        }

        $ids = $this->configured;

        if ($this->model !== null) {
            try {
                $ids = [...$ids, ...$this->model::query()->pluck('icon')->all()];
            } catch (Throwable $exception) {
                // A missing table (migrations not run yet) must not break the picker.
                report($exception);
            }
        }

        // Entries may be Blade Icons names ("heroicon-o-trash"); compare by "provider:name".
        $keys = array_map(static function (mixed $id): string {
            $id = (string) $id;

            return str_contains($id, ':') ? $id : (app(IconRegistry::class)->findByBladeName($id)?->key() ?? $id);
        }, $ids);

        return $this->hidden = array_fill_keys($keys, true);
    }
}
