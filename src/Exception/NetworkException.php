<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Exception;

class NetworkException extends IrisException
{
    public function __construct(
        string $message = 'Network error communicating with IRIS API',
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
