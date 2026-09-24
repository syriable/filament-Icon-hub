<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\IconHub\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Syriable\Filament\Plugins\IconHub\Database\Factories\ManagedIconFactory;

/**
 * An icon uploaded by an administrator. The SVG column only ever contains
 * sanitized markup.
 *
 * @property int $id
 * @property string $name
 * @property string $label
 * @property string|null $collection
 * @property list<string>|null $tags
 * @property string $svg
 * @property bool $is_enabled
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class ManagedIcon extends Model
{
    /** @use HasFactory<ManagedIconFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    public function getTable(): string
    {
        $table = config('icon-hub.library.tables.icons');

        return is_string($table) ? $table : 'icon_hub_icons';
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'is_enabled' => 'boolean',
        ];
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeEnabled(Builder $query): void
    {
        $query->where('is_enabled', true);
    }

    protected static function newFactory(): ManagedIconFactory
    {
        return ManagedIconFactory::new();
    }
}
