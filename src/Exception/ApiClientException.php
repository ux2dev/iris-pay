<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Exception;

/**
 * Thrown for 4xx HTTP responses (client errors: bad request, unauthorized, not found, etc.).
 * These are not retryable.
 */
class ApiClientException extends InvalidResponseException
{
}
