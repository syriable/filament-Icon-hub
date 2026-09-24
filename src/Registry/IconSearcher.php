<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\IconHub\Registry;

use Syriable\Filament\Plugins\IconHub\Contracts\IconVisibility;
use Syriable\Filament\Plugins\IconHub\Data\Icon;
use Syriable\Filament\Plugins\IconHub\Data\IconQuery;
use Syriable\Filament\Plugins\IconHub\Data\SearchPage;
use Syriable\Filament\Plugins\IconHub\Enums\ProviderStatus;
use Syriable\Filament\Plugins\IconHub\Exceptions\ProviderException;
use Throwable;

/**
 * Runs a search across one or more providers and pages through them in
 * order with an opaque cursor ("{provider index}.{page}"). Hidden icons are
 * removed and every provider failure is isolated and reported per provider.
 */
final readonly class IconSearcher
{
    /** Upper bound of provider calls per request, protecting against runaway loops. */
    private const int MAX_CALLS = 10;

    public function __construct(
        private IconRegistry $registry,
        private IconVisibility $visibility,
    ) {}

    /**
     * @param  list<string>  $providers  provider ids, in order
     */
    public function search(IconQuery $query, array $providers, ?string $cursor = null): SearchPage
    {
        [$index, $page] = $this->parseCursor($cursor, $query->page);
        $single = count($providers) === 1;

        $icons = [];
        $errors = [];
        $calls = 0;

        while ($index < count($providers) && count($icons) < $query->perPage && $calls < self::MAX_CALLS) {
            $id = $providers[$index];
            $status = $this->registry->status($id);

            if (! $status->isAvailable()) {
                if ($single || $status === ProviderStatus::Unavailable) {
                    $errors[$id] = $status->value;
                }

                [$index, $page] = [$index + 1, 1];

                continue;
            }

            $calls++;

            try {
                $results = $this->registry->provider($id)->search($query->forPage($page));
            } catch (ProviderException $exception) {
                report($exception);
                $errors[$id] = $exception->reason;
                [$index, $page] = [$index + 1, 1];

                continue;
            } catch (Throwable $exception) {
                report($exception);
                $errors[$id] = ProviderException::UNAVAILABLE;
                [$index, $page] = [$index + 1, 1];

                continue;
            }

            foreach ($results->icons as $icon) {
                if ($icon->provider === $id && ! $this->visibility->isHidden($icon->key())) {
                    $icons[] = $icon;
                }
            }

            [$index, $page] = $results->hasMore && ! $results->isEmpty()
                ? [$index, $page + 1]
                : [$index + 1, 1];
        }

        return new SearchPage(
            icons: $this->unique($icons),
            nextCursor: $index < count($providers) ? "{$index}.{$page}" : null,
            errors: $errors,
        );
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function parseCursor(?string $cursor, int $defaultPage): array
    {
        if ($cursor === null || preg_match('/^(\d{1,4})\.(\d{1,6})$/', $cursor, $match) !== 1) {
            return [0, $defaultPage];
        }

        return [(int) $match[1], max(1, (int) $match[2])];
    }

    /**
     * @param  list<Icon>  $icons
     * @return list<Icon>
     */
    private function unique(array $icons): array
    {
        $unique = [];

        foreach ($icons as $icon) {
            $unique[$icon->key()] ??= $icon;
        }

        return array_values($unique);
    }
}
