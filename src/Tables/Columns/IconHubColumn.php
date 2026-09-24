<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\IconHub\Tables\Columns;

use Filament\Support\Components\Contracts\HasEmbeddedView;
use Filament\Support\Enums\Alignment;
use Filament\Tables\Columns\Column;
use Syriable\Filament\Plugins\IconHub\Concerns\DisplaysIcons;

/**
 * Displays an icon identifier (or list of identifiers) stored by IconPicker.
 */
class IconHubColumn extends Column implements HasEmbeddedView
{
    use DisplaysIcons;

    public function toEmbeddedHtml(): string
    {
        $alignment = $this->getAlignment();

        $attributes = $this->getExtraAttributeBag()->class([
            'fi-ta-icon-hub',
            ($alignment instanceof Alignment) ? "fi-align-{$alignment->value}" : (is_string($alignment) ? $alignment : ''),
        ]);

        $html = $this->renderIconsHtml($this->getState(), 'fi-ta-icon-hub-icons');

        if ($html === '' && filled($placeholder = $this->getPlaceholder())) {
            $html = '<p class="fi-ta-placeholder">'.e($placeholder).'</p>';
        }

        return '<div '.$attributes->toHtml().'>'.$html.'</div>';
    }
}
