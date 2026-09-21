<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Api\Request;

final readonly class BulkEntry
{
    public function __construct(
        public string $receiverIban,
        public string $receiverName,
        public string $remittanceDescription,
        public float $sum,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'receiverIban' => $this->receiverIban,
            'receiverName' => $this->receiverName,
            'remittanceDescription' => $this->remittanceDescription,
            'sum' => $this->sum,
        ];
    }
}
