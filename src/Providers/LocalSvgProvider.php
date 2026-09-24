<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\IconHub\Providers;

use Illuminate\Support\Str;
use Syriable\Filament\Plugins\IconHub\Data\Icon;
use Syriable\Filament\Plugins\IconHub\Data\IconSource;
use Syriable\Filament\Plugins\IconHub\Support\IconName;

/**
 * Serves SVG files from a local directory. Nested folders become categories
 * and are part of the icon name: "brand:social/github".
 *
 * Files are only scanned when the cached index is missing; SVG contents are
 * sanitized and cached on render.
 */
final class LocalSvgProvider extends IndexedIconProvider
{
    /**
     * @param  array<string, string>  $variants  file name prefix => label
     */
    public function __construct(
        private readonly string $id,
        private readonly string $path,
        private readonly ?string $label = null,
        array $variants = [],
    ) {
        $this->useVariantPrefixes($variants);
    }

    public function id(): string
    {
        return $this->id;
    }

    public function label(): string
    {
        return $this->label ?? Str::headline($this->id);
    }

    public function path(): string
    {
        return $this->path;
    }

    protected function indexCacheKey(): array
    {
        return [$this->path, $this->variantLabels];
    }

    protected function buildIndex(): iterable
    {
        foreach ($this->scanSvgFiles($this->path) as ['name' => $name, 'path' => $absolutePath]) {
            $segments = explode('/', $name);
            $fileName = (string) array_pop($segments);
            [$variant, $baseName] = $this->splitVariant($fileName);

            yield new Icon(
                provider: $this->id,
                name: $name,
                label: IconName::label($baseName),
                source: IconSource::svgFile($absolutePath),
                category: $segments !== [] ? implode('/', $segments) : null,
                variant: $variant,
                tags: IconName::tags(implode(' ', [...$segments, $baseName])),
            );
        }
    }
}
