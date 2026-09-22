<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Api\Response;

final readonly class ExchangeRate
{
    public function __construct(
        public ?string $currencyFrom,
        public ?string $rateFrom,
        public ?string $currencyTo,
        public ?string $rateTo,
        public ?string $rateDate,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            currencyFrom: $data['currencyFrom'] ?? null,
            rateFrom: $data['rateFrom'] ?? null,
            currencyTo: $data['currencyTo'] ?? null,
            rateTo: $data['rateTo'] ?? null,
            rateDate: $data['rateDate'] ?? null,
        );
    }
}
