<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\IconHub\Forms\Components;

use Closure;
use Filament\Forms\Components\Select;
use Filament\Support\Components\Attributes\ExposedLivewireMethod;
use Illuminate\Support\Str;
use Livewire\Attributes\Renderless;
use Syriable\Filament\Plugins\IconHub\Data\Icon;
use Syriable\Filament\Plugins\IconHub\Data\IconQuery;
use Syriable\Filament\Plugins\IconHub\Registry\IconRegistry;
use Syriable\Filament\Plugins\IconHub\Rendering\IconRenderer;
use Syriable\Filament\Plugins\IconHub\Rules\ValidIcon;
use Throwable;

/**
 * A compact alternative to {@see IconPicker}: Filament's native searchable
 * Select, with every option rendered as "icon + label". It stores the same
 * "provider:name" identifiers and uses the same registry, so both fields are
 * interchangeable.
 *
 * Options are only loaded when the dropdown opens (and on search), never on
 * form render.
 */
class IconSelect extends Select
{
    /** @var list<string>|Closure|null */
    protected array|Closure|null $iconProviders = null;

    protected bool|Closure $isGroupedByProvider = true;

    protected bool|Closure $isGrid = true;

    /** @var list<array<array-key, mixed>|Closure> */
    protected array $extraIconAttributes = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->searchable();
        $this->allowHtml();
        $this->native(false);
        $this->options([]);
        $this->dynamicOptions();

        $this->getSearchResultsUsing(static fn (IconSelect $component, ?string $search): array => $component->getIconOptions((string) $search));

        $this->getOptionLabelUsing(static fn (IconSelect $component, mixed $value): ?string => $component->getIconOptionLabel($value));

        $this->getOptionLabelsUsing(static function (IconSelect $component, array $values): array {
            $labels = [];

            foreach ($values as $value) {
                if (is_string($value) && ($label = $component->getIconOptionLabel($value)) !== null) {
                    $labels[$value] = $label;
                }
            }

            return $labels;
        });

        $this->rule(static fn (IconSelect $component): ValidIcon => new ValidIcon($component->getProviderIds()));

        $this->extraAttributes(static fn (IconSelect $component): array => [
            'class' => $component->isGrid() ? 'fi-icon-hub-select fi-icon-hub-select-grid' : 'fi-icon-hub-select',
        ], merge: true);
    }

    /**
     * Show the dropdown options as a grid of icon tiles (default) instead of
     * a list. Names stay available as tooltips and to screen readers, and
     * the selected value always shows the icon with its name.
     */
    public function grid(bool|Closure $condition = true): static
    {
        $this->isGrid = $condition;

        return $this;
    }

    public function isGrid(): bool
    {
        return (bool) $this->evaluate($this->isGrid);
    }

    /**
     * Limit (and order) the providers offered by this select.
     *
     * @param  list<string>|Closure|null  $providers
     */
    public function providers(array|Closure|null $providers): static
    {
        $this->iconProviders = $providers;

        return $this;
    }

    /**
     * Group options under their provider's label when several providers are offered.
     */
    public function groupByProvider(bool|Closure $condition = true): static
    {
        $this->isGroupedByProvider = $condition;

        return $this;
    }

    /**
     * Attributes applied to every rendered icon: class, style, data-*, aria-*, x-*, ...
     *
     * @param  array<array-key, mixed>|Closure  $attributes
     */
    public function extraIconAttributes(array|Closure $attributes, bool $merge = false): static
    {
        $this->extraIconAttributes = $merge ? [...$this->extraIconAttributes, $attributes] : [$attributes];

        return $this;
    }

    /**
     * @return list<string>
     */
    public function getProviderIds(): array
    {
        $providers = $this->evaluate($this->iconProviders) ?? config('icon-hub.picker.providers');

        return array_keys($this->registry()->providers(is_array($providers) ? array_values(array_filter($providers, is_string(...))) : null));
    }

    public function isGroupedByProvider(): bool
    {
        return (bool) $this->evaluate($this->isGroupedByProvider) && count($this->getProviderIds()) > 1;
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
     * Icons shown when the dropdown opens, before anything is typed.
     *
     * @return array<array<string, mixed>>
     */
    #[ExposedLivewireMethod]
    #[Renderless]
    public function getOptionsForJs(): array
    {
        return $this->transformOptionsForJs($this->getIconOptions(''));
    }

    /**
     * Search the registry and build Select options (optionally grouped by provider).
     *
     * @return array<string, string>|array<string, array<string, string>>
     */
    public function getIconOptions(string $search): array
    {
        $providers = $this->getProviderIds();

        if ($providers === [] || $this->isDisabled()) {
            return [];
        }

        $page = $this->registry()->search(
            new IconQuery(search: $search, perPage: $this->getOptionsLimit()),
            $providers,
        );

        /** @var array<string, array<string, string>> $grouped */
        $grouped = [];
        /** @var array<string, string> $flat */
        $flat = [];

        foreach ($page->icons as $icon) {
            $label = $this->renderOptionLabel($icon);

            $grouped[$this->providerLabel($icon->provider)][$icon->key()] = $label;
            $flat[$icon->key()] = $label;
        }

        return $this->isGroupedByProvider() ? $grouped : $flat;
    }

    public function getIconOptionLabel(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        $icon = $this->registry()->find($value);

        return $icon === null ? null : $this->renderOptionLabel($icon);
    }

    /**
     * Option labels are HTML (allowHtml): a sanitized icon and an escaped label.
     */
    protected function renderOptionLabel(Icon $icon): string
    {
        $svg = app(IconRenderer::class)->render($icon, $this->getExtraIconAttributes())->toHtml();

        // Variants share labels ("Star" outline / solid / mini), so name them.
        $variantLabel = $icon->variant !== null ? Str::headline($icon->variant) : null;
        $variant = $variantLabel !== null
            ? ' <span class="fi-icon-hub-option-meta">'.e($variantLabel).'</span>'
            : '';
        $title = $variantLabel !== null ? "{$icon->label} ({$variantLabel})" : $icon->label;

        return '<span class="fi-icon-hub-option" title="'.e($title).'">'
            .'<span class="fi-icon-hub-option-icon">'.$svg.'</span>'
            .'<span class="fi-icon-hub-option-label">'.e($icon->label).'</span>'
            .$variant
            .'</span>';
    }

    private function providerLabel(string $provider): string
    {
        try {
            return $this->registry()->provider($provider)->label();
        } catch (Throwable) {
            return $provider;
        }
    }

    private function registry(): IconRegistry
    {
        return app(IconRegistry::class);
    }
}
