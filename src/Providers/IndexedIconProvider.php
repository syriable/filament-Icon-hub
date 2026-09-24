<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\IconHub\Providers;

use Illuminate\Support\Str;
use Symfony\Component\Finder\Finder;
use Syriable\Filament\Plugins\IconHub\Cache\IconCache;
use Syriable\Filament\Plugins\IconHub\Contracts\IconProvider;
use Syriable\Filament\Plugins\IconHub\Data\Icon;
use Syriable\Filament\Plugins\IconHub\Data\IconQuery;
use Syriable\Filament\Plugins\IconHub\Data\IconResults;
use Syriable\Filament\Plugins\IconHub\Data\ProviderMetadata;
use Syriable\Filament\Plugins\IconHub\Enums\ProviderStatus;

/**
 * Base class for providers whose complete icon list can be enumerated, such
 * as Blade Icons sets or local SVG directories.
 *
 * Subclasses only build the list of icons. The list is cached as plain
 * arrays and searched in memory, so the filesystem is never scanned on a
 * regular request.
 *
 * @phpstan-import-type IconArray from Icon
 */
abstract class IndexedIconProvider implements IconProvider
{
    /** @var array<string, IconArray>|null */
    private ?array $index = null;

    /** @var array<string, string> variant value => label */
    protected array $variantLabels = [];

    /** @var array<string, string> name prefix => variant label */
    private array $variantPrefixes = [];

    /**
     * Enumerate every icon of this provider. Called only on a cache miss.
     *
     * @return iterable<Icon>
     */
    abstract protected function buildIndex(): iterable;

    /**
     * Extra values that invalidate the cached index when they change
     * (e.g. source paths).
     *
     * @return array<mixed>
     */
    protected function indexCacheKey(): array
    {
        return [];
    }

    public function status(): ProviderStatus
    {
        return ProviderStatus::Available;
    }

    public function metadata(): ProviderMetadata
    {
        $categories = [];
        $variants = [];

        foreach ($this->index() as $entry) {
            if ($entry['category'] !== null) {
                $categories[$entry['category']] ??= $this->categoryLabel($entry['category']);
            }

            if ($entry['variant'] !== null) {
                $variants[$entry['variant']] ??= $this->variantLabels[$entry['variant']] ?? Str::headline($entry['variant']);
            }
        }

        asort($categories, SORT_NATURAL | SORT_FLAG_CASE);

        return new ProviderMetadata(
            categories: $categories,
            variants: $variants,
            remote: false,
            total: count($this->index()),
        );
    }

    public function search(IconQuery $query): IconResults
    {
        $terms = $query->hasSearch()
            ? preg_split('/\s+/', mb_strtolower($query->search), -1, PREG_SPLIT_NO_EMPTY) ?: []
            : [];

        $matches = [];

        foreach ($this->index() as $entry) {
            if ($query->category !== null && $entry['category'] !== $query->category) {
                continue;
            }

            if ($query->variant !== null && $entry['variant'] !== $query->variant) {
                continue;
            }

            $score = $this->score($entry, $terms);

            if ($score !== null) {
                $matches[$score][] = $entry;
            }
        }

        ksort($matches);
        $matches = $matches === [] ? [] : array_merge(...$matches);

        $page = array_slice($matches, $query->offset(), $query->perPage);

        return new IconResults(
            icons: array_map(static fn (array $entry): Icon => Icon::fromArray($entry), $page),
            hasMore: $query->offset() + $query->perPage < count($matches),
            total: count($matches),
        );
    }

    public function find(string $name): ?Icon
    {
        $entry = $this->index()[$name] ?? null;

        return $entry === null ? null : Icon::fromArray($entry);
    }

    /**
     * Drop the in-memory copy of the index; the cached copy is cleared
     * through {@see IconCache::flush()}.
     */
    public function forgetIndex(): void
    {
        $this->index = null;
    }

    /**
     * Configure name prefixes that mark a variant, e.g. ['o-' => 'Outline'].
     *
     * @param  array<string, string>  $prefixes  prefix => label
     */
    protected function useVariantPrefixes(array $prefixes): void
    {
        $this->variantPrefixes = $prefixes;
        $this->variantLabels = [];

        foreach ($prefixes as $label) {
            $this->variantLabels[Str::slug($label)] = $label;
        }
    }

    /**
     * Split a name into its variant (if a configured prefix matches) and the
     * remaining base name.
     *
     * @return array{0: ?string, 1: string}
     */
    protected function splitVariant(string $name): array
    {
        foreach ($this->variantPrefixes as $prefix => $label) {
            if ($prefix !== '' && str_starts_with($name, $prefix) && strlen($name) > strlen($prefix)) {
                return [Str::slug($label), substr($name, strlen($prefix))];
            }
        }

        return [null, $name];
    }

    protected function categoryLabel(string $category): string
    {
        return Str::headline($category);
    }

    protected function cache(): IconCache
    {
        return app(IconCache::class);
    }

    /**
     * Recursively list SVG files as relative path (without extension) and
     * absolute path pairs. A list (not a map) so numeric file names such as
     * "123.svg" stay strings.
     *
     * @return list<array{name: string, path: string}>
     */
    protected function scanSvgFiles(string $directory): array
    {
        if (! is_dir($directory)) {
            return [];
        }

        $files = [];
        $finder = Finder::create()->files()->in($directory)->name('*.svg')->ignoreDotFiles(true)->sortByName();

        foreach ($finder as $file) {
            $relative = str_replace('\\', '/', $file->getRelativePathname());
            $files[] = ['name' => substr($relative, 0, -4), 'path' => $file->getRealPath() ?: $file->getPathname()];
        }

        return $files;
    }

    /**
     * @return array<string, IconArray>
     */
    private function index(): array
    {
        return $this->index ??= $this->cache()->remember(
            $this->id(),
            IconCache::TYPE_INDEX,
            [static::class, $this->indexCacheKey()],
            function (): array {
                $index = [];

                foreach ($this->buildIndex() as $icon) {
                    $index[$icon->name] = $icon->toArray();
                }

                return $this->orderByVariant($index);
            },
        );
    }

    /**
     * Keep the configured variant order (e.g. outline before solid) so an
     * unfiltered listing starts with the primary variant, not interleaved.
     *
     * @param  array<string, IconArray>  $index
     * @return array<string, IconArray>
     */
    private function orderByVariant(array $index): array
    {
        if ($this->variantLabels === []) {
            return $index;
        }

        $order = array_flip(array_keys($this->variantLabels));
        $position = 0;
        $positions = array_map(static function () use (&$position): int {
            return $position++;
        }, $index);

        uksort($index, static function (string $a, string $b) use ($index, $order, $positions): int {
            $variantA = $order[$index[$a]['variant'] ?? ''] ?? PHP_INT_MAX;
            $variantB = $order[$index[$b]['variant'] ?? ''] ?? PHP_INT_MAX;

            return [$variantA, $positions[$a]] <=> [$variantB, $positions[$b]];
        });

        return $index;
    }

    /**
     * Lower score ranks higher; null means "no match".
     *
     * @param  IconArray  $entry
     * @param  list<string>  $terms
     */
    private function score(array $entry, array $terms): ?int
    {
        if ($terms === []) {
            return 0;
        }

        $name = mb_strtolower($entry['name']);
        $label = mb_strtolower($entry['label']);
        $haystack = implode(' ', [$name, $label, implode(' ', $entry['tags']), mb_strtolower((string) $entry['category'])]);

        foreach ($terms as $term) {
            if (! str_contains($haystack, $term)) {
                return null;
            }
        }

        $phrase = implode(' ', $terms);

        return match (true) {
            $label === $phrase, $name === $phrase => 0,
            str_starts_with($label, $phrase) => 1,
            str_contains($label, $phrase) => 2,
            default => 3,
        };
    }
}
