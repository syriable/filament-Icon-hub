<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\IconHub\Providers;

use Illuminate\Database\Eloquent\Builder;
use Syriable\Filament\Plugins\IconHub\Contracts\IconProvider;
use Syriable\Filament\Plugins\IconHub\Data\Icon;
use Syriable\Filament\Plugins\IconHub\Data\IconQuery;
use Syriable\Filament\Plugins\IconHub\Data\IconResults;
use Syriable\Filament\Plugins\IconHub\Data\IconSource;
use Syriable\Filament\Plugins\IconHub\Data\ProviderMetadata;
use Syriable\Filament\Plugins\IconHub\Enums\ProviderStatus;
use Syriable\Filament\Plugins\IconHub\Models\ManagedIcon;
use Syriable\Filament\Plugins\IconHub\Support\IconName;

/**
 * Icons uploaded by administrators and stored in the database. Collections
 * are exposed as categories. Disabled icons are not offered by the picker,
 * but icons that are already stored keep rendering.
 */
final readonly class LibraryIconProvider implements IconProvider
{
    /**
     * @param  class-string<ManagedIcon>  $model
     */
    public function __construct(
        private string $id = 'library',
        private string $label = 'Library',
        private string $model = ManagedIcon::class,
    ) {}

    public function id(): string
    {
        return $this->id;
    }

    public function label(): string
    {
        return $this->label;
    }

    public function status(): ProviderStatus
    {
        return ProviderStatus::Available;
    }

    public function metadata(): ProviderMetadata
    {
        $categories = $this->query()
            ->whereNotNull('collection')
            ->distinct()
            ->orderBy('collection')
            ->pluck('collection')
            ->filter(static fn (mixed $value): bool => is_string($value))
            ->mapWithKeys(static fn (string $collection): array => [$collection => $collection])
            ->all();

        return new ProviderMetadata(categories: $categories, total: $this->query()->count());
    }

    public function search(IconQuery $query): IconResults
    {
        $builder = $this->query()
            ->when($query->category !== null, static fn (Builder $builder) => $builder->where('collection', $query->category))
            ->orderBy('label')
            ->orderBy('id');

        $terms = preg_split('/\s+/', $query->search, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        foreach ($terms as $term) {
            $like = '%'.addcslashes(mb_strtolower($term), '%_\\').'%';

            $builder->where(static function (Builder $builder) use ($like): void {
                foreach (['name', 'label', 'collection', 'tags'] as $column) {
                    $builder->orWhereRaw("LOWER({$builder->getQuery()->getGrammar()->wrap($column)}) LIKE ?", [$like]);
                }
            });
        }

        $models = $builder->offset($query->offset())->limit($query->perPage + 1)->get();

        return new IconResults(
            icons: $models->take($query->perPage)->map(fn (ManagedIcon $icon): Icon => $this->toIcon($icon))->values()->all(),
            hasMore: $models->count() > $query->perPage,
        );
    }

    public function find(string $name): ?Icon
    {
        $model = $this->model::query()->where('name', $name)->first();

        return $model === null ? null : $this->toIcon($model);
    }

    public function toIcon(ManagedIcon $icon): Icon
    {
        return new Icon(
            provider: $this->id,
            name: $icon->name,
            label: $icon->label,
            source: IconSource::svg($icon->svg),
            category: $icon->collection,
            tags: array_values(array_unique([...($icon->tags ?? []), ...IconName::tags($icon->name)])),
        );
    }

    /**
     * @return Builder<ManagedIcon>
     */
    private function query(): Builder
    {
        return $this->model::query()->where('is_enabled', true);
    }
}
