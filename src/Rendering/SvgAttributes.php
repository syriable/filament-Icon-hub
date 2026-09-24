<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\IconHub\Rendering;

use Stringable;

/**
 * Merges developer supplied attributes into the root element of SVG markup
 * or renders them as an attribute string. Values are always escaped; names
 * are validated so arbitrary markup can never be injected.
 *
 * Supports class, style, data-*, aria-*, x-*, @click, :class and friends.
 */
final class SvgAttributes
{
    private const string NAME_PATTERN = '/^[a-zA-Z_:@][a-zA-Z0-9_:@.\-]*$/';

    /**
     * @param  array<array-key, mixed>  $attributes
     * @return array<string, string|true>
     */
    public static function normalize(array $attributes): array
    {
        $normalized = [];

        foreach ($attributes as $name => $value) {
            if (is_int($name)) {
                if (is_string($value)) {
                    [$name, $value] = [$value, true];
                } else {
                    continue;
                }
            }

            if (preg_match(self::NAME_PATTERN, $name) !== 1 || $value === null || $value === false) {
                continue;
            }

            if ($value === true) {
                $normalized[$name] = true;

                continue;
            }

            if (is_array($value)) {
                $value = implode(' ', array_filter(array_map(strval(...), array_filter($value, is_scalar(...)))));
            }

            if (! is_scalar($value) && ! $value instanceof Stringable) {
                continue;
            }

            $normalized[$name] = (string) $value;
        }

        return $normalized;
    }

    /**
     * @param  array<array-key, mixed>  $attributes
     */
    public static function toHtml(array $attributes): string
    {
        $html = [];

        foreach (self::normalize($attributes) as $name => $value) {
            $html[] = $value === true ? $name : $name.'="'.e($value).'"';
        }

        return implode(' ', $html);
    }

    /**
     * Merge attributes into the root <svg> tag. "class" values are appended,
     * `false` removes an attribute, everything else replaces existing values.
     *
     * @param  array<array-key, mixed>  $attributes
     */
    public static function merge(string $svg, array $attributes): string
    {
        $removed = array_keys(array_filter($attributes, static fn (mixed $value): bool => $value === false));
        $attributes = self::normalize($attributes);

        if (($attributes === [] && $removed === []) || preg_match('/<svg\b([^>]*?)(\/?)>/is', $svg, $match, PREG_OFFSET_CAPTURE) !== 1) {
            return $svg;
        }

        $existing = self::parse($match[1][0]);

        foreach ($removed as $name) {
            if (is_string($name) && ($existingName = self::findName($existing, $name)) !== null) {
                unset($existing[$existingName]);
            }
        }

        foreach ($attributes as $name => $value) {
            $existingName = self::findName($existing, $name);

            if ($existingName !== null) {
                if (strtolower($name) === 'class' && is_string($value) && is_string($existing[$existingName])) {
                    $value = trim($existing[$existingName].' '.$value);
                }

                unset($existing[$existingName]);
            }

            $existing[$name] = $value;
        }

        $tag = '<svg'.(($rendered = self::toHtml($existing)) !== '' ? ' '.$rendered : '').$match[2][0].'>';

        return substr_replace($svg, $tag, $match[0][1], strlen($match[0][0]));
    }

    /**
     * Case-insensitive attribute lookup that preserves the original casing
     * (SVG attributes such as viewBox are case-sensitive).
     *
     * @param  array<string, string|true>  $attributes
     */
    private static function findName(array $attributes, string $name): ?string
    {
        foreach (array_keys($attributes) as $existing) {
            if (strcasecmp($existing, $name) === 0) {
                return $existing;
            }
        }

        return null;
    }

    /**
     * @return array<string, string|true>
     */
    private static function parse(string $attributeString): array
    {
        preg_match_all(
            '/([^\s=\/>"\']+)(?:\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>"\']+)))?/',
            $attributeString,
            $matches,
            PREG_SET_ORDER | PREG_UNMATCHED_AS_NULL,
        );

        $attributes = [];

        foreach ($matches as $match) {
            $name = (string) $match[1];
            $value = $match[2] ?? $match[3] ?? $match[4] ?? null;

            $attributes[$name] = $value === null ? true : html_entity_decode($value, ENT_QUOTES | ENT_HTML5);
        }

        return $attributes;
    }
}
