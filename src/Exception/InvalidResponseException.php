<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Exception;

class InvalidResponseException extends IrisException
{
    /** @var array<string, mixed> */
    private readonly array $responseData;

    /**
     * @param array<string, mixed> $responseData
     */
    public function __construct(
        string $message,
        array $responseData = [],
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        $this->responseData = $responseData;
        parent::__construct($message, $code, $previous);
    }

    /** @return array<string, mixed> */
    public function getResponseData(): array
    {
        return $this->responseData;
    }
}
