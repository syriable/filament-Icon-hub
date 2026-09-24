<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\IconHub\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Syriable\Filament\Plugins\IconHub\Models\ManagedIcon;

/**
 * @extends Factory<ManagedIcon>
 */
final class ManagedIconFactory extends Factory
{
    protected $model = ManagedIcon::class;

    public function definition(): array
    {
        $name = fake()->unique()->slug(2);

        return [
            'name' => $name,
            'label' => ucfirst(str_replace('-', ' ', $name)),
            'collection' => null,
            'tags' => [],
            'svg' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/></svg>',
            'is_enabled' => true,
        ];
    }

    public function disabled(): self
    {
        return $this->state(['is_enabled' => false]);
    }
}
