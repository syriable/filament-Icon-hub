<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\IconHub\Infolists\Components;

use Filament\Infolists\Components\Entry;
use Filament\Support\Components\Contracts\HasEmbeddedView;
use Syriable\Filament\Plugins\IconHub\Concerns\DisplaysIcons;

/**
 * Displays an icon identifier (or list of identifiers) in an infolist.
 */
class IconHubEntry extends Entry implements HasEmbeddedView
{
    use DisplaysIcons;

    public function toEmbeddedHtml(): string
    {
        $attributes = $this->getExtraAttributeBag()->class(['fi-in-icon-hub']);

        $html = $this->renderIconsHtml($this->getState(), 'fi-in-icon-hub-icons');

        if ($html === '' && filled($placeholder = $this->getPlaceholder())) {
            $html = '<p class="fi-in-placeholder">'.e($placeholder).'</p>';
        }

        return $this->wrapEmbeddedHtml('<div '.$attributes->toHtml().'>'.$html.'</div>');
    }
}
