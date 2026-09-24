@php
    use Filament\Support\Facades\FilamentAsset;
    use Filament\Support\Icons\Heroicon;

    $statePath = $getStatePath();
    $isDisabled = $isDisabled();
    $isMultiple = $isMultiple();
    $isSearchable = $isSearchable();
    $modalId = $getModalId();
    $placeholder = $getPlaceholder() ?? __('icon-hub::icon-hub.picker.placeholder');
    $fieldId = $getId();
    $messages = [
        'noProviders' => __('icon-hub::icon-hub.empty.no_providers'),
        'noResults' => __('icon-hub::icon-hub.empty.no_results'),
        'noIcons' => __('icon-hub::icon-hub.empty.no_icons'),
        'providerUnavailable' => __('icon-hub::icon-hub.empty.provider_unavailable'),
        'selectedCount' => __('icon-hub::icon-hub.picker.selected_count'),
        'loaded' => __('icon-hub::icon-hub.picker.loaded'),
        'loadFailed' => __('icon-hub::icon-hub.errors.request_failed'),
    ];
@endphp

<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div
        x-load
        x-load-src="{{ FilamentAsset::getAlpineComponentSrc('icon-hub-picker', 'syriable/filament-icon-hub') }}"
        x-data="iconHubPicker({
            state: $wire.{{ $applyStateBindingModifiers("\$entangle('{$statePath}')") }},
            componentKey: @js($getKey()),
            modalId: @js($modalId),
            isMultiple: @js($isMultiple),
            providers: @js($getProvidersForJs()),
            showProviderFilter: @js($shouldShowProviderFilter()),
            messages: @js($messages),
        })"
        {{
            $getExtraAttributeBag()
                ->merge($getExtraAlpineAttributes(), escape: false)
                ->class(['fi-icon-hub-picker'])
        }}
    >
        {{-- Kept out of x-data so Livewire morphs never re-initialize the component. --}}
        <script type="application/json" x-ref="selectedIcons" wire:ignore>{!! json_encode((object) $getSelectedIconsForJs(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>

        <div class="fi-icon-hub-control">
            <button
                type="button"
                @if ($fieldId) id="{{ $fieldId }}" @endif
                aria-haspopup="dialog"
                @disabled($isDisabled)
                x-on:click="open()"
                {{
                    $getExtraTriggerAttributeBag()->class([
                        'fi-icon-hub-trigger',
                        'fi-disabled' => $isDisabled,
                    ])
                }}
            >
                <span wire:ignore class="fi-icon-hub-trigger-content">
                    <template x-if="! isMultiple && selectedList().length">
                        <span class="fi-icon-hub-trigger-selected">
                            <span class="fi-icon-hub-preview" x-html="selectedList()[0].html"></span>
                            <span class="fi-icon-hub-trigger-label" x-text="selectedList()[0].label"></span>
                        </span>
                    </template>

                    <template x-if="isMultiple || ! selectedList().length">
                        <span class="fi-icon-hub-trigger-placeholder">
                            {{ \Filament\Support\generate_icon_html(Heroicon::OutlinedSquares2x2) }}
                            <span>{{ $placeholder }}</span>
                        </span>
                    </template>
                </span>
            </button>

            @if (! $isDisabled)
                <button
                    type="button"
                    class="fi-icon-hub-clear"
                    x-cloak
                    x-show="selectedList().length"
                    x-on:click="clear()"
                    aria-label="{{ __('icon-hub::icon-hub.picker.clear') }}"
                    title="{{ __('icon-hub::icon-hub.picker.clear') }}"
                >
                    {{ \Filament\Support\generate_icon_html(Heroicon::XMark) }}
                </button>
            @endif
        </div>

        @if ($isMultiple)
            <ul wire:ignore class="fi-icon-hub-chips" x-cloak x-show="selectedList().length" aria-label="{{ __('icon-hub::icon-hub.picker.selected') }}">
                <template x-for="icon in selectedList()" x-bind:key="icon.id">
                    <li class="fi-icon-hub-chip">
                        <span class="fi-icon-hub-preview" x-html="icon.html"></span>
                        <span class="fi-icon-hub-chip-label" x-text="icon.label"></span>
                        @if (! $isDisabled)
                            <button
                                type="button"
                                class="fi-icon-hub-chip-remove"
                                x-on:click="toggle(icon)"
                                x-bind:aria-label="@js(__('icon-hub::icon-hub.picker.remove')) + ' ' + icon.label"
                            >
                                {{ \Filament\Support\generate_icon_html(Heroicon::XMark, size: \Filament\Support\Enums\IconSize::Small) }}
                            </button>
                        @endif
                    </li>
                </template>
            </ul>
        @endif

        @if (! $isDisabled)
            <x-filament::modal
                :id="$modalId"
                :heading="$getModalHeading()"
                :width="$getModalWidth()"
                class="fi-icon-hub-modal"
            >
                <div wire:ignore class="fi-icon-hub-body">
                    <div class="fi-icon-hub-toolbar">
                        @if ($isSearchable)
                            <x-filament::input.wrapper
                                :prefix-icon="Heroicon::MagnifyingGlass"
                                class="fi-icon-hub-search"
                            >
                                <x-filament::input
                                    type="search"
                                    x-ref="search"
                                    x-model="search"
                                    x-on:input.debounce.300ms="reload()"
                                    x-on:keydown.down.prevent="focusGrid()"
                                    x-on:keydown.enter.prevent="focusGrid()"
                                    :placeholder="__('icon-hub::icon-hub.picker.search_placeholder')"
                                    :aria-label="__('icon-hub::icon-hub.picker.search_placeholder')"
                                    autocomplete="off"
                                />

                                <span x-show="isSearching" x-cloak class="fi-icon-hub-search-loading" aria-hidden="true">
                                    <x-filament::loading-indicator class="fi-icon-hub-spinner" />
                                </span>
                            </x-filament::input.wrapper>
                        @endif

                        <div class="fi-icon-hub-filters">
                            <template x-if="showProviderFilter && providers.length > 1">
                                <label class="fi-icon-hub-filter">
                                    <span class="fi-sr-only">{{ __('icon-hub::icon-hub.picker.provider') }}</span>
                                    <x-filament::input.wrapper>
                                        <x-filament::input.select x-model="provider" x-on:change="changeProvider()">
                                            <option value="">{{ __('icon-hub::icon-hub.picker.all_providers') }}</option>
                                            <template x-for="item in providers" x-bind:key="item.id">
                                                <option
                                                    x-bind:value="item.id"
                                                    x-bind:disabled="! item.available"
                                                    x-text="item.available ? item.label : item.label + ' (' + @js(__('icon-hub::icon-hub.picker.unavailable')) + ')'"
                                                ></option>
                                            </template>
                                        </x-filament::input.select>
                                    </x-filament::input.wrapper>
                                </label>
                            </template>

                            <template x-if="filters.variants.length">
                                <label class="fi-icon-hub-filter">
                                    <span class="fi-sr-only">{{ __('icon-hub::icon-hub.picker.variant') }}</span>
                                    <x-filament::input.wrapper>
                                        <x-filament::input.select x-model="variant" x-on:change="reload()">
                                            <option value="">{{ __('icon-hub::icon-hub.picker.all_variants') }}</option>
                                            <template x-for="item in filters.variants" x-bind:key="item.value">
                                                <option x-bind:value="item.value" x-text="item.label"></option>
                                            </template>
                                        </x-filament::input.select>
                                    </x-filament::input.wrapper>
                                </label>
                            </template>

                            <template x-if="filters.categories.length">
                                <label class="fi-icon-hub-filter">
                                    <span class="fi-sr-only">{{ __('icon-hub::icon-hub.picker.category') }}</span>
                                    <x-filament::input.wrapper>
                                        <x-filament::input.select x-model="category" x-on:change="reload()">
                                            <option value="">{{ __('icon-hub::icon-hub.picker.all_categories') }}</option>
                                            <template x-for="item in filters.categories" x-bind:key="item.value">
                                                <option x-bind:value="item.value" x-text="item.label"></option>
                                            </template>
                                        </x-filament::input.select>
                                    </x-filament::input.wrapper>
                                </label>
                            </template>
                        </div>
                    </div>

                    <template x-if="errors.length">
                        <ul class="fi-icon-hub-errors" role="status">
                            <template x-for="error in errors" x-bind:key="error.provider">
                                <li class="fi-icon-hub-error">
                                    {{ \Filament\Support\generate_icon_html(Heroicon::OutlinedExclamationTriangle, size: \Filament\Support\Enums\IconSize::Small) }}
                                    <span>
                                        <strong x-text="error.label"></strong>:
                                        <span x-text="error.message"></span>
                                    </span>
                                </li>
                            </template>
                        </ul>
                    </template>

                    <div class="fi-icon-hub-scroll" x-ref="scroll">
                        <div x-show="isLoading && ! icons.length" class="fi-icon-hub-grid" aria-hidden="true">
                            <template x-for="i in 24" x-bind:key="i">
                                <span class="fi-icon-hub-skeleton"></span>
                            </template>
                        </div>

                        <div
                            x-show="icons.length"
                            x-ref="grid"
                            role="listbox"
                            aria-label="{{ __('icon-hub::icon-hub.picker.icons') }}"
                            @if ($isMultiple) aria-multiselectable="true" @endif
                            class="fi-icon-hub-grid"
                            x-bind:aria-busy="isLoading"
                            x-on:keydown="onGridKeydown($event)"
                        >
                            <template x-for="(icon, index) in icons" x-bind:key="icon.id">
                                <div
                                    role="option"
                                    class="fi-icon-hub-item"
                                    x-bind:id="optionId(index)"
                                    x-bind:tabindex="index === activeIndex ? 0 : -1"
                                    x-bind:aria-selected="isSelected(icon) ? 'true' : 'false'"
                                    x-bind:aria-label="icon.label"
                                    x-bind:title="icon.label"
                                    x-bind:class="{ 'fi-selected': isSelected(icon) }"
                                    x-on:click="choose(icon, index)"
                                    x-on:focus="activeIndex = index"
                                >
                                    <span class="fi-icon-hub-item-icon" x-html="icon.html"></span>
                                    <span class="fi-icon-hub-item-label" x-text="icon.label"></span>
                                </div>
                            </template>
                        </div>

                        <div
                            x-show="! isLoading && ! icons.length"
                            x-cloak
                            class="fi-icon-hub-empty"
                            role="status"
                        >
                            {{ \Filament\Support\generate_icon_html(Heroicon::OutlinedMagnifyingGlass, size: \Filament\Support\Enums\IconSize::ExtraLarge) }}
                            <p class="fi-icon-hub-empty-heading" x-text="emptyHeading()"></p>
                        </div>

                        <div x-ref="sentinel" class="fi-icon-hub-sentinel" aria-hidden="true"></div>

                        <div x-show="isLoadingMore" x-cloak class="fi-icon-hub-more">
                            <x-filament::loading-indicator class="fi-icon-hub-spinner" />
                            <span>{{ __('icon-hub::icon-hub.picker.loading_more') }}</span>
                        </div>
                    </div>

                    <p class="fi-sr-only" role="status" aria-live="polite" x-text="announcement"></p>
                </div>

                @if ($isMultiple)
                    <x-slot name="footer">
                        <div class="fi-icon-hub-footer">
                            <span class="fi-icon-hub-count" x-text="selectedCountLabel()"></span>
                            <x-filament::button x-on:click="close()">
                                {{ __('icon-hub::icon-hub.picker.done') }}
                            </x-filament::button>
                        </div>
                    </x-slot>
                @endif
            </x-filament::modal>
        @endif

    </div>
</x-dynamic-component>
