<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\IconHub\Enums;

/**
 * Describes how an icon is rendered. Providers never emit HTML themselves;
 * they describe where the icon comes from and the renderer draws it safely.
 *
 * New cases may be added in minor releases, so `match` statements on this
 * enum should always include a default arm.
 */
enum SourceKind: string
{
    /** A Blade Icons name such as "heroicon-o-user". Trusted, installed by the developer. */
    case Blade = 'blade';

    /** Inline SVG markup. Treated as untrusted and sanitized on render. */
    case Svg = 'svg';

    /** Absolute path to a local SVG file. Sanitized on render and cached. */
    case SvgFile = 'svg-file';

    /** An image URL rendered through an <img> tag. Never fetched by the server. */
    case Url = 'url';
}
