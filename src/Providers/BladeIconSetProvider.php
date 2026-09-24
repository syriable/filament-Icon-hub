<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\IconHub\Providers;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Syriable\Filament\Plugins\IconHub\Data\Icon;
use Syriable\Filament\Plugins\IconHub\Data\IconSource;
use Syriable\Filament\Plugins\IconHub\Support\IconName;

/**
 * Exposes one registered Blade Icons set (heroicons, fontawesome-solid,
 * lucide, tabler, ...) as an icon provider. Icons render through Blade Icons.
 */
final class BladeIconSetProvider extends IndexedIconProvider
{
    /**
     * @param  list<string>  $paths
     * @param  array<string, string>  $variants  name prefix => label, e.g. ['o-' => 'Outline']
     */
    public function __construct(
        private readonly string $set,
        private readonly string $prefix,
        private readonly array $paths,
        private readonly ?string $disk = null,
        private readonly ?string $label = null,
        array $variants = [],
    ) {
        $this->useVariantPrefixes($variants);
    }

    /**
     * Build a provider from a Blade Icons set definition (Factory::all()).
     *
     * @param  array<string, mixed>  $options
     * @param  array<string, string>  $variants
     */
    public static function fromSet(string $set, array $options, ?string $label = null, array $variants = []): self
    {
        $paths = array_values(array_filter((array) ($options['paths'] ?? []), is_string(...)));
        $disk = $options['disk'] ?? null;

        return new self(
            set: $set,
            prefix: is_string($options['prefix'] ?? null) ? $options['prefix'] : $set,
            paths: $paths,
            disk: is_string($disk) && $disk !== '' ? $disk : null,
            label: $label,
            variants: $variants,
        );
    }

    public function id(): string
    {
        return $this->set;
    }

    public function label(): string
    {
        return $this->label ?? Str::headline($this->set);
    }

    public function prefix(): string
    {
        return $this->prefix;
    }

    protected function indexCacheKey(): array
    {
        return [$this->prefix, $this->paths, $this->disk, $this->variantLabels];
    }

    protected function buildIndex(): iterable
    {
        foreach ($this->files() as $name) {
            [$variant, $baseName] = $this->splitVariant($name);
            $segments = explode('.', $baseName);

            yield new Icon(
                provider: $this->set,
                name: $name,
                label: IconName::label((string) end($segments)),
                source: IconSource::blade("{$this->prefix}-{$name}"),
                category: count($segments) > 1 ? $segments[0] : null,
                variant: $variant,
                tags: IconName::tags($baseName),
            );
        }
    }

    /**
     * Blade Icons names: relative path without extension, "/" replaced by ".".
     *
     * @return list<string>
     */
    private function files(): array
    {
        $names = [];

        foreach ($this->paths as $path) {
            if ($this->disk !== null) {
                $root = trim($path, '/');

                foreach (Storage::disk($this->disk)->allFiles($root) as $file) {
                    if (str_ends_with($file, '.svg')) {
                        $names[] = str_replace('/', '.', substr(ltrim(substr($file, strlen($root)), '/'), 0, -4));
                    }
                }

                continue;
            }

            foreach ($this->scanSvgFiles($path) as $file) {
                $names[] = str_replace('/', '.', $file['name']);
            }
        }

        return array_values(array_unique($names));
    }
}
