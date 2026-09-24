<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\IconHub\View\Components;

use Closure;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\View\Component;
use Syriable\Filament\Plugins\IconHub\Registry\IconRegistry;

/**
 * <x-icon-hub::icon icon="heroicons:o-user" class="h-5 w-5" />
 */
final class Icon extends Component
{
    public function __construct(
        public ?string $icon = null,
    ) {}

    public function render(): Closure
    {
        return function (array $data): string {
            /** @var \Illuminate\View\ComponentAttributeBag $attributes */
            $attributes = $data['attributes'];

            $html = app(IconRegistry::class)->html($this->icon, $attributes->getAttributes());

            return $html instanceof Htmlable ? $html->toHtml() : '';
        };
    }
}
