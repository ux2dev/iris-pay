<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Api\Request;

use Ux2Dev\Iris\Api\Enum\IdentifierType;

final readonly class BulkBudgetEntry
{
    public function __construct(
        public string $receiverIban,
        public string $receiverName,
        public string $remittanceDescription,
        public float $sum,
        public string $identifier,
        public IdentifierType $identifierType,
        public ?string $paymentCategory = null,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $data = [
            'receiverIban' => $this->receiverIban,
            'receiverName' => $this->receiverName,
            'remittanceDescription' => $this->remittanceDescription,
            'sum' => $this->sum,
            'identifier' => $this->identifier,
            'identifierType' => $this->identifierType->value,
        ];

        if ($this->paymentCategory !== null) { $data['paymentCategory'] = $this->paymentCategory; }

        return $data;
    }
}
