<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\IconHub\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * An icon identifier ("provider:name") that must not be offered by the picker.
 * The source icon itself is never modified.
 *
 * @property int $id
 * @property string $icon
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class HiddenIcon extends Model
{
    protected $guarded = ['id'];

    public function getTable(): string
    {
        $table = config('icon-hub.library.tables.hidden_icons');

        return is_string($table) ? $table : 'icon_hub_hidden_icons';
    }
}
