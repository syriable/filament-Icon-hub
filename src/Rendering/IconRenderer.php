<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\IconHub\Rendering;

use BladeUI\Icons\Exceptions\SvgNotFound;
use BladeUI\Icons\Factory as BladeIconFactory;
use Illuminate\Support\HtmlString;
use Syriable\Filament\Plugins\IconHub\Cache\IconCache;
use Syriable\Filament\Plugins\IconHub\Data\Icon;
use Syriable\Filament\Plugins\IconHub\Enums\SourceKind;
use Throwable;

/**
 * The single place where icons become HTML. Every source kind is rendered
 * defensively: untrusted SVG is sanitized, URLs are scheme-checked and
 * attributes are escaped.
 */
final class IconRenderer
{
    /**
     * @param  list<string>  $allowedUrlSchemes
     */
    public function __construct(
        private readonly SvgSanitizer $sanitizer,
        private readonly IconCache $cache,
        private readonly BladeIconFactory $bladeIcons,
        private readonly array $allowedUrlSchemes = ['https'],
    ) {}

    /**
     * @param  array<array-key, mixed>  $attributes
     */
    public function render(Icon $icon, array $attributes = []): HtmlString
    {
        $attributes = $this->withAccessibilityDefaults($attributes);

        try {
            $html = match ($icon->source->kind) {
                SourceKind::Blade => $this->renderSvg($this->bladeSvg($icon->source->value), $attributes),
                SourceKind::Svg => $this->renderSvg($this->sanitizer->sanitize($icon->source->value), $attributes),
                SourceKind::SvgFile => $this->renderSvg($this->svgFile($icon->provider, $icon->source->value), $attributes),
                SourceKind::Url => $this->renderUrl($icon->source->value, $icon->label, $attributes),
            };
        } catch (Throwable $exception) {
            report($exception);

            $html = '';
        }

        return new HtmlString($html);
    }

    public function isSafeUrl(string $url): bool
    {
        $parts = parse_url($url);

        if ($parts === false || ! isset($parts['scheme'], $parts['host']) || isset($parts['user']) || isset($parts['pass'])) {
            return false;
        }

        return in_array(strtolower($parts['scheme']), $this->allowedUrlSchemes, true);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function renderSvg(?string $svg, array $attributes): string
    {
        return $svg === null ? '' : SvgAttributes::merge($svg, $attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function renderUrl(string $url, string $label, array $attributes): string
    {
        if (! $this->isSafeUrl($url)) {
            return '';
        }

        $alt = ($attributes['aria-hidden'] ?? null) === 'true' ? '' : $label;
        unset($attributes['focusable']);

        $attributes = ['src' => $url, 'alt' => $alt, 'loading' => 'lazy', 'decoding' => 'async', ...$attributes];

        return '<img '.SvgAttributes::toHtml($attributes).'>';
    }

    private function bladeSvg(string $name): ?string
    {
        // Blade Icons does not escape attributes, so only raw contents are
        // taken from it and attributes are merged by SvgAttributes instead.
        try {
            return trim($this->bladeIcons->svg($name)->contents()) ?: null;
        } catch (SvgNotFound) {
            return null;
        }
    }

    private function svgFile(string $provider, string $path): ?string
    {
        $modified = @filemtime($path);

        if ($modified === false || ! is_file($path)) {
            return null;
        }

        $svg = $this->cache->remember(
            $provider,
            IconCache::TYPE_SVG,
            [$path, $modified],
            function () use ($path): string {
                $contents = @file_get_contents($path);

                return is_string($contents) ? ($this->sanitizer->sanitize($contents) ?? '') : '';
            },
        );

        return $svg !== '' ? $svg : null;
    }

    /**
     * Icons are decorative unless the developer names them.
     *
     * @param  array<array-key, mixed>  $attributes
     * @return array<array-key, mixed>
     */
    private function withAccessibilityDefaults(array $attributes): array
    {
        $isLabelled = isset($attributes['aria-label']) || isset($attributes['aria-labelledby']);

        if (! array_key_exists('aria-hidden', $attributes)) {
            // Many icon sets bake aria-hidden into their markup; a labelled icon must drop it.
            $attributes['aria-hidden'] = $isLabelled ? false : 'true';
        }

        if ($isLabelled) {
            $attributes['role'] ??= 'img';
        }

        $attributes['focusable'] ??= 'false';

        return $attributes;
    }
}
