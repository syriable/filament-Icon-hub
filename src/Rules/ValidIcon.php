<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\IconHub\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Syriable\Filament\Plugins\IconHub\Registry\IconRegistry;
use Syriable\Filament\Plugins\IconHub\ValueObjects\IconId;

/**
 * Validates an icon identifier (or a list of them): correct format, allowed
 * provider and an icon that actually exists.
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
        $id = IconId::tryParse($value);

        if ($id === null) {
            return false;
        }

        if ($this->providers !== null && ! in_array($id->provider, $this->providers, true)) {
            return false;
        }

        return app(IconRegistry::class)->find($id) !== null;
    }
}
