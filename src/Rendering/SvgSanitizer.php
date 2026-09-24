<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\IconHub\Rendering;

use enshrined\svgSanitize\Sanitizer;
use Throwable;

/**
 * Turns untrusted SVG markup into safe inline markup.
 *
 * Scripts, event handlers, foreign objects, external references and
 * javascript: URLs are removed by an allow-list sanitizer. Anything that does
 * not produce a single root <svg> element is rejected.
 */
final readonly class SvgSanitizer
{
    public function __construct(
        private int $maxBytes = 262_144,
    ) {}

    public function sanitize(string $markup): ?string
    {
        if ($markup === '' || strlen($markup) > $this->maxBytes || stripos($markup, '<svg') === false) {
            return null;
        }

        $sanitizer = new Sanitizer;
        $sanitizer->removeRemoteReferences(true);
        $sanitizer->removeXMLTag(true);
        $sanitizer->minify(true);

        try {
            $clean = $sanitizer->sanitize($markup);
        } catch (Throwable) {
            return null;
        }

        if (! is_string($clean)) {
            return null;
        }

        $clean = trim($clean);

        if (preg_match('/^<svg\b[^>]*>.*<\/svg>$/is', $clean) !== 1) {
            return null;
        }

        return $clean;
    }
}
