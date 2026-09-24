<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\IconHub\Forms\Components;

use Closure;
use Filament\Forms\Components\Concerns\HasPlaceholder;
use Filament\Forms\Components\Field;
use Filament\Support\Components\Attributes\ExposedLivewireMethod;
use Filament\Support\Concerns\HasExtraAlpineAttributes;
use Filament\Support\Enums\Width;
use Filament\Support\View\ComponentAttributeBag;
use Livewire\Attributes\Renderless;
use Syriable\Filament\Plugins\IconHub\Data\Icon;
use Syriable\Filament\Plugins\IconHub\Data\IconQuery;
use Syriable\Filament\Plugins\IconHub\Registry\IconRegistry;
use Syriable\Filament\Plugins\IconHub\Rendering\IconRenderer;
use Syriable\Filament\Plugins\IconHub\Rules\ValidIcon;
use Syriable\Filament\Plugins\IconHub\ValueObjects\IconId;
use Throwable;

/**
 * A Filament form field that stores an icon identifier ("provider:name"),
 * or a list of them when multiple(). Icons are loaded page by page from the
 * registry only once the picker is opened.
 */
class IconPicker extends Field
{
    use HasExtraAlpineAttributes;
    use HasPlaceholder;

    protected string $view = 'icon-hub::forms.components.icon-picker';

    /** @var list<string>|Closure|null */
    protected array|Closure|null $providers = null;

    protected bool|Closure $isMultiple = false;

    protected bool|Closure $isSearchable = true;

    protected bool|Closure|null $shouldShowProviderFilter = null;

    protected bool|Closure $shouldShowCategoryFilter = true;

    protected bool|Closure $shouldShowVariantFilter = true;

    protected int|Closure|null $perPage = null;

    protected string|Closure|null $modalHeading = null;

    protected Width|string|Closure|null $modalWidth = null;

    /** @var list<array<array-key, mixed>|Closure> */
    protected array $extraIconAttributes = [];

    /** @var list<array<array-key, mixed>|Closure> */
    protected array $extraTriggerAttributes = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->default(static fn (IconPicker $component): ?array => $component->isMultiple() ? [] : null);

        $this->afterStateHydrated(static function (IconPicker $component, mixed $state): void {
            $component->state($component->normalizeStoredValues($component->normalizeState($state)));
        });

        $this->dehydrateStateUsing(static fn (IconPicker $component, mixed $state): string|array|null => $component->normalizeState($state));

        $this->rule(static fn (IconPicker $component): ValidIcon => new ValidIcon($component->getProviderIds()));
    }

    /**
     * Limit (and order) the providers offered by this picker.
     *
     * @param  list<string>|Closure|null  $providers
     */
    public function providers(array|Closure|null $providers): static
    {
        $this->providers = $providers;

        return $this;
    }

    public function multiple(bool|Closure $condition = true): static
    {
        $this->isMultiple = $condition;

        return $this;
    }

    public function searchable(bool|Closure $condition = true): static
    {
        $this->isSearchable = $condition;

        return $this;
    }

    /**
     * Null (default) shows the provider filter when more than one provider is offered.
     */
    public function showProviderFilter(bool|Closure|null $condition = true): static
    {
        $this->shouldShowProviderFilter = $condition;

        return $this;
    }

    public function showCategoryFilter(bool|Closure $condition = true): static
    {
        $this->shouldShowCategoryFilter = $condition;

        return $this;
    }

    public function showVariantFilter(bool|Closure $condition = true): static
    {
        $this->shouldShowVariantFilter = $condition;

        return $this;
    }

    public function perPage(int|Closure|null $perPage): static
    {
        $this->perPage = $perPage;

        return $this;
    }

    public function modalHeading(string|Closure|null $heading): static
    {
        $this->modalHeading = $heading;

        return $this;
    }

    public function modalWidth(Width|string|Closure|null $width): static
    {
        $this->modalWidth = $width;

        return $this;
    }

    /**
     * Attributes applied to every rendered icon (grid items and the selected
     * preview): class, style, data-*, aria-*, x-*, ...
     *
     * @param  array<array-key, mixed>|Closure  $attributes
     */
    public function extraIconAttributes(array|Closure $attributes, bool $merge = false): static
    {
        $this->extraIconAttributes = $merge ? [...$this->extraIconAttributes, $attributes] : [$attributes];

        return $this;
    }

    /**
     * Attributes applied to the button that opens the picker.
     *
     * @param  array<array-key, mixed>|Closure  $attributes
     */
    public function extraTriggerAttributes(array|Closure $attributes, bool $merge = false): static
    {
        $this->extraTriggerAttributes = $merge ? [...$this->extraTriggerAttributes, $attributes] : [$attributes];

        return $this;
    }

    /**
     * @return list<string>
     */
    public function getProviderIds(): array
    {
        $providers = $this->evaluate($this->providers) ?? config('icon-hub.picker.providers');

        return array_keys($this->registry()->providers(is_array($providers) ? array_values(array_filter($providers, is_string(...))) : null));
    }

    public function isMultiple(): bool
    {
        return (bool) $this->evaluate($this->isMultiple);
    }

    public function isSearchable(): bool
    {
        return (bool) $this->evaluate($this->isSearchable);
    }

    public function shouldShowProviderFilter(): bool
    {
        return $this->evaluate($this->shouldShowProviderFilter) ?? count($this->getProviderIds()) > 1;
    }

    public function shouldShowCategoryFilter(): bool
    {
        return (bool) $this->evaluate($this->shouldShowCategoryFilter);
    }

    public function shouldShowVariantFilter(): bool
    {
        return (bool) $this->evaluate($this->shouldShowVariantFilter);
    }

    public function getPerPage(): int
    {
        return (int) ($this->evaluate($this->perPage) ?? config('icon-hub.picker.per_page', 60));
    }

    public function getModalHeading(): string
    {
        return $this->evaluate($this->modalHeading) ?? __('icon-hub::icon-hub.picker.modal_heading');
    }

    public function getModalWidth(): Width|string
    {
        return $this->evaluate($this->modalWidth) ?? Width::FourExtraLarge;
    }

    public function getModalId(): string
    {
        return 'icon-hub-picker-'.md5((string) ($this->getKey() ?? $this->getStatePath()));
    }

    /**
     * @return array<array-key, mixed>
     */
    public function getExtraIconAttributes(): array
    {
        return $this->mergeAttributes($this->extraIconAttributes);
    }

    public function getExtraTriggerAttributeBag(): ComponentAttributeBag
    {
        return new ComponentAttributeBag($this->mergeAttributes($this->extraTriggerAttributes));
    }

    /**
     * @return list<array{id: string, label: string, available: bool, status: string}>
     */
    public function getProvidersForJs(): array
    {
        $providers = [];

        foreach ($this->registry()->providers($this->getProviderIds()) as $id => $provider) {
            $status = $this->registry()->status($id);

            $providers[] = [
                'id' => $id,
                'label' => $this->safeLabel(static fn (): string => $provider->label(), $id),
                'available' => $status->isAvailable(),
                'status' => $status->value,
            ];
        }

        return $providers;
    }

    /**
     * The currently selected icons, pre-rendered for the trigger preview.
     *
     * @return array<string, array{id: string, label: string, provider: string, html: string}>
     */
    public function getSelectedIconsForJs(): array
    {
        $state = $this->normalizeState($this->getState());
        $selected = [];

        foreach (is_array($state) ? $state : array_filter([$state]) as $id) {
            $icon = $this->registry()->find($id);

            $selected[$id] = $icon !== null
                ? $this->iconForJs($icon)
                : ['id' => $id, 'label' => $id, 'provider' => (string) IconId::tryParse($id)?->provider, 'html' => ''];
        }

        return $selected;
    }

    /**
     * Called from the browser. Returns one page of icons plus, when asked,
     * the category and variant filters of the selected provider.
     *
     * @return array{
     *     icons: list<array{id: string, label: string, provider: string, html: string}>,
     *     next: ?string,
     *     errors: list<array{provider: string, label: string, message: string}>,
     *     filters: array{categories: list<array{value: string, label: string}>, variants: list<array{value: string, label: string}>}|null,
     * }
     */
    #[ExposedLivewireMethod]
    #[Renderless]
    public function searchIcons(
        string $search = '',
        ?string $provider = null,
        ?string $category = null,
        ?string $variant = null,
        ?string $cursor = null,
        bool $withFilters = false,
    ): array {
        $allowed = $this->getProviderIds();

        if ($this->isDisabled() || $allowed === [] || ($provider !== null && ! in_array($provider, $allowed, true))) {
            return ['icons' => [], 'next' => null, 'errors' => [], 'filters' => null];
        }

        $single = $provider !== null || count($allowed) === 1;
        $providers = $provider !== null ? [$provider] : $allowed;

        $query = new IconQuery(
            search: $this->isSearchable() ? $search : '',
            category: $single && $this->shouldShowCategoryFilter() ? $this->nullIfBlank($category) : null,
            variant: $single && $this->shouldShowVariantFilter() ? $this->nullIfBlank($variant) : null,
            perPage: $this->getPerPage(),
        );

        $page = $this->registry()->search($query, $providers, $this->nullIfBlank($cursor));

        $errors = [];

        foreach ($page->errors as $id => $reason) {
            $errors[] = [
                'provider' => $id,
                'label' => $this->safeLabel(fn (): string => $this->registry()->provider($id)->label(), $id),
                'message' => $this->errorMessage($reason),
            ];
        }

        return [
            'icons' => array_map($this->iconForJs(...), $page->icons),
            'next' => $page->nextCursor,
            'errors' => $errors,
            'filters' => $withFilters && $single ? $this->filtersFor($providers[0]) : null,
        ];
    }

    /**
     * Called from the browser when the state changed on the server (for
     * example through $set()) and previews for new ids are needed.
     *
     * @param  array<mixed>  $ids  untrusted input from the browser
     * @return array<string, array{id: string, label: string, provider: string, html: string}>
     */
    #[ExposedLivewireMethod]
    #[Renderless]
    public function getIconsForJs(array $ids): array
    {
        $allowed = $this->getProviderIds();
        $icons = [];

        foreach (array_slice(array_values(array_filter($ids, is_string(...))), 0, 100) as $id) {
            $icon = $this->registry()->find($id);

            if ($icon !== null && in_array($icon->provider, $allowed, true)) {
                $icons[$id] = $this->iconForJs($icon);
            }
        }

        return $icons;
    }

    /**
     * Convert stored values to the configured format, e.g. a legacy
     * "heroicons:o-user" becomes "heroicon-o-user".
     *
     * @param  string|list<string>|null  $state
     * @return string|list<string>|null
     */
    public function normalizeStoredValues(string|array|null $state): string|array|null
    {
        if (is_string($state)) {
            return $this->registry()->normalizeValue($state);
        }

        return is_array($state)
            ? array_values(array_unique(array_map(fn (string $value): string => $this->registry()->normalizeValue($value), $state)))
            : null;
    }

    /**
     * @return string|list<string>|null
     */
    public function normalizeState(mixed $state): string|array|null
    {
        if ($this->isMultiple()) {
            $values = is_array($state) ? $state : (is_string($state) && $state !== '' ? [$state] : []);

            return array_values(array_unique(array_filter($values, static fn (mixed $value): bool => is_string($value) && $value !== '')));
        }

        if (is_array($state)) {
            $state = reset($state);
        }

        return is_string($state) && $state !== '' ? $state : null;
    }

    /**
     * @return array{id: string, label: string, provider: string, html: string}
     */
    protected function iconForJs(Icon $icon): array
    {
        return [
            'id' => $this->registry()->storedValue($icon),
            'label' => $icon->label,
            'provider' => $icon->provider,
            'html' => app(IconRenderer::class)->render($icon, $this->getExtraIconAttributes())->toHtml(),
        ];
    }

    /**
     * @return array{categories: list<array{value: string, label: string}>, variants: list<array{value: string, label: string}>}
     */
    protected function filtersFor(string $provider): array
    {
        $metadata = $this->registry()->metadata($provider);
        $options = static function (array $items): array {
            $options = [];

            foreach ($items as $value => $label) {
                $options[] = ['value' => (string) $value, 'label' => (string) $label];
            }

            return $options;
        };

        return [
            'categories' => $this->shouldShowCategoryFilter() ? $options($metadata->categories) : [],
            'variants' => $this->shouldShowVariantFilter() ? $options($metadata->variants) : [],
        ];
    }

    protected function errorMessage(string $reason): string
    {
        $key = "icon-hub::icon-hub.errors.{$reason}";
        $message = __($key);

        return $message === $key ? __('icon-hub::icon-hub.errors.unavailable') : $message;
    }

    /**
     * @param  list<array<array-key, mixed>|Closure>  $attributes
     * @return array<array-key, mixed>
     */
    protected function mergeAttributes(array $attributes): array
    {
        $merged = [];

        foreach ($attributes as $set) {
            $evaluated = $this->evaluate($set);

            if (! is_array($evaluated)) {
                continue;
            }

            if (isset($merged['class'], $evaluated['class']) && is_string($merged['class']) && is_string($evaluated['class'])) {
                $evaluated['class'] = trim($merged['class'].' '.$evaluated['class']);
            }

            $merged = [...$merged, ...$evaluated];
        }

        return $merged;
    }

    private function safeLabel(Closure $label, string $fallback): string
    {
        try {
            return (string) $label();
        } catch (Throwable $exception) {
            report($exception);

            return $fallback;
        }
    }

    private function nullIfBlank(?string $value): ?string
    {
        return $value === null || trim($value) === '' ? null : $value;
    }

    private function registry(): IconRegistry
    {
        return app(IconRegistry::class);
    }
}
