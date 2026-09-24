<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\IconHub\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Syriable\Filament\Plugins\IconHub\Registry\IconRegistry;

/**
 * Validates a stored icon value (or a list of them): a Blade Icons name or a
 * "provider:name" identifier, from an allowed provider, that actually exists.
 */
final readonly class ValidIcon implements ValidationRule
{
    /**
     * @param  list<string>|null  $providers  allowed provider ids; null allows all
     */
    public function __construct(
        private ?array $providers = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '' || $value === []) {
            return;
        }

        foreach (is_array($value) ? $value : [$value] as $item) {
            if (! $this->passes($item)) {
                $fail('icon-hub::icon-hub.validation.invalid')->translate();

                return;
            }
        }
    }

    private function passes(mixed $value): bool
    {
        if (! is_string($value)) {
            return false;
        }

        // Accepts Blade Icons names ("heroicon-o-user") and "provider:name".
        $icon = app(IconRegistry::class)->find($value);

        return $icon !== null && ($this->providers === null || in_array($icon->provider, $this->providers, true));
    }
}
