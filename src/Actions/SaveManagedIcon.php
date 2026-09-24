<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\IconHub\Actions;

use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Syriable\Filament\Plugins\IconHub\Data\ManagedIconData;
use Syriable\Filament\Plugins\IconHub\Models\ManagedIcon;
use Syriable\Filament\Plugins\IconHub\Rendering\SvgSanitizer;
use Syriable\Filament\Plugins\IconHub\Support\IconName;

/**
 * Create or update an uploaded icon. The SVG is sanitized before it is
 * stored; markup that cannot be made safe is rejected.
 */
final readonly class SaveManagedIcon
{
    public function __construct(
        private SvgSanitizer $sanitizer,
    ) {}

    /**
     * @throws ValidationException
     */
    public function handle(ManagedIconData $data, ?ManagedIcon $icon = null): ManagedIcon
    {
        $svg = $this->sanitizer->sanitize($data->svg);

        if ($svg === null) {
            throw ValidationException::withMessages([
                'svg' => __('icon-hub::icon-hub.library.validation.invalid_svg'),
            ]);
        }

        /** @var class-string<ManagedIcon> $model */
        $model = config('icon-hub.library.models.icon', ManagedIcon::class);
        $icon ??= new $model;

        $name = Str::slug($data->name ?? $data->label ?? '');

        if ($name === '') {
            throw ValidationException::withMessages([
                'name' => __('icon-hub::icon-hub.library.validation.name_required'),
            ]);
        }

        $taken = $model::query()
            ->where('name', $name)
            ->when($icon->exists, fn ($query) => $query->whereKeyNot($icon->getKey()))
            ->exists();

        if ($taken) {
            throw ValidationException::withMessages([
                'name' => __('icon-hub::icon-hub.library.validation.name_taken'),
            ]);
        }

        $collection = $data->collection !== null ? Str::of($data->collection)->squish()->toString() : null;

        $icon->fill([
            'name' => $name,
            'label' => $data->label ?? IconName::label($name),
            'collection' => $collection !== '' ? $collection : null,
            'tags' => array_values(array_unique(array_map(mb_strtolower(...), $data->tags))),
            'svg' => $svg,
            'is_enabled' => $data->isEnabled,
        ]);

        $icon->save();

        return $icon;
    }
}
