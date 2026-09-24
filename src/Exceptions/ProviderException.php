<?php

declare(strict_types=1);

namespace Syriable\Filament\Plugins\IconHub\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Thrown by providers when a source cannot be used. The `reason` is a short,
 * safe code shown to users; the message is for logs only and must never be
 * sent to the browser.
 */
final class ProviderException extends RuntimeException
{
    public const string TIMEOUT = 'timeout';

    public const string RATE_LIMITED = 'rate_limited';

    public const string UNAUTHORIZED = 'unauthorized';

    public const string INVALID_RESPONSE = 'invalid_response';

    public const string UNAVAILABLE = 'unavailable';

    public const string UNCONFIGURED = 'unconfigured';

    public function __construct(
        public readonly string $provider,
        public readonly string $reason,
        string $message = '',
        ?Throwable $previous = null,
    ) {
        parent::__construct($message !== '' ? $message : "Icon provider [{$provider}] failed: {$reason}.", 0, $previous);
    }

    public static function timeout(string $provider, ?Throwable $previous = null): self
    {
        return new self($provider, self::TIMEOUT, "Icon provider [{$provider}] timed out or could not be reached.", $previous);
    }

    public static function rateLimited(string $provider): self
    {
        return new self($provider, self::RATE_LIMITED, "Icon provider [{$provider}] is rate limited.");
    }

    public static function unauthorized(string $provider, int $status): self
    {
        return new self($provider, self::UNAUTHORIZED, "Icon provider [{$provider}] rejected the credentials (HTTP {$status}).");
    }

    public static function invalidResponse(string $provider, string $detail = '', ?Throwable $previous = null): self
    {
        return new self($provider, self::INVALID_RESPONSE, trim("Icon provider [{$provider}] returned an invalid response. {$detail}"), $previous);
    }

    public static function unconfigured(string $provider): self
    {
        return new self($provider, self::UNCONFIGURED, "Icon provider [{$provider}] is not configured.");
    }

    public static function unavailable(string $provider, string $detail = '', ?Throwable $previous = null): self
    {
        return new self($provider, self::UNAVAILABLE, trim("Icon provider [{$provider}] is unavailable. {$detail}"), $previous);
    }
}
