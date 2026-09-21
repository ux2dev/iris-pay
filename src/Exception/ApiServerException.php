<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Exception;

/**
 * Thrown for 5xx HTTP responses (server errors: internal error, service unavailable, etc.).
 * These may be retryable.
 */
class ApiServerException extends InvalidResponseException
{
}
