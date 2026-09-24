<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\IconHub\Concerns;

use Closure;
use Filament\Support\Enums\IconSize;
use Illuminate\Support\Collection;
use Syriable\Filament\Plugins\IconHub\Registry\IconRegistry;

/**
 * Shared rendering for the table column and the infolist entry.
 *
 * @internal
 */
trait DisplaysIcons
{
    protected IconSize|string|Closure|null $iconSize = null;

    protected bool|Closure $isIconLabelShown = false;

    /** @var list<array<array-key, mixed>|Closure> */
    protected array $extraIconAttributes = [];

    public function size(IconSize|string|Closure|null $size): static
    {
        $this->iconSize = $size;

        return $this;
    }

    public function showLabel(bool|Closure $condition = true): static
    {
        $this->isIconLabelShown = $condition;

        return $this;
    }

    /**
     * @param  array<array-key, mixed>|Closure  $attributes
     */
    public function extraIconAttributes(array|Closure $attributes, bool $merge = false): static
    {
        $this->extraIconAttributes = $merge ? [...$this->extraIconAttributes, $attributes] : [$attributes];

        return $this;
    }

    public function getIconSize(): string
    {
        $size = $this->evaluate($this->iconSize) ?? IconSize::Medium;

        return $size instanceof IconSize ? $size->value : (string) $size;
    }

    public function isIconLabelShown(): bool
    {
        return (bool) $this->evaluate($this->isIconLabelShown);
    }

    /**
     * @return array<array-key, mixed>
     */
    public function getExtraIconAttributes(): array
    {
        $merged = [];

        foreach ($this->extraIconAttributes as $attributes) {
            $evaluated = $this->evaluate($attributes);

            if (is_array($evaluated)) {
                $merged = [...$merged, ...$evaluated];
            }
        }

        return $merged;
    }

    /**
     * Render the state (one id or a list of ids). Lookups hit the registry's
     * memo and provider caches, never an uncached remote API per row.
     */
    protected function renderIconsHtml(mixed $state, string $class): string
    {
        if ($state instanceof Collection) {
            $state = $state->all();
        }

        $ids = array_values(array_filter(is_array($state) ? $state : [$state], static fn (mixed $id): bool => is_string($id) && $id !== ''));

        if ($ids === []) {
            return '';
        }

        $registry = app(IconRegistry::class);
        $showLabel = $this->isIconLabelShown();
        $attributes = $this->getExtraIconAttributes();
        $items = [];

        foreach ($ids as $id) {
            $icon = $registry->find($id);

            if ($icon === null) {
                continue;
            }

            $html = $registry->html($icon, $showLabel ? $attributes : [...$attributes, 'aria-label' => $icon->label, 'role' => 'img'])->toHtml();
            $label = $showLabel ? '<span class="fi-icon-hub-display-label">'.e($icon->label).'</span>' : '';

            $items[] = '<span class="fi-icon-hub-display-item" title="'.e($icon->label).'"><span class="fi-icon-hub-display-icon">'.$html.'</span>'.$label.'</span>';
        }

        if ($items === []) {
            return '';
        }

        return '<span class="'.e($class).' fi-icon-hub-display fi-size-'.e($this->getIconSize()).'">'.implode('', $items).'</span>';
    }
}
