<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Api\Request;

use Ux2Dev\Iris\Api\Enum\IdentifierType;

final readonly class BudgetDirectData
{
    public function __construct(
        public string $bankHash,
        public string $receiverIban,
        public string $receiverName,
        public string $remittanceDescription,
        public float $sum,
        public string $currency,
        public string $hookHash,
        public string $identifier,
        public IdentifierType $identifierType,
        public bool $emailNotification = false,
        public ?string $description = null,
        public ?string $senderIban = null,
        public ?string $psuId = null,
        public ?string $paymentCategory = null,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $data = [
            'bankHash' => $this->bankHash,
            'receiverIban' => $this->receiverIban,
            'receiverName' => $this->receiverName,
            'remittanceDescription' => $this->remittanceDescription,
            'sum' => $this->sum,
            'currency' => $this->currency,
            'hookHash' => $this->hookHash,
            'emailNotification' => $this->emailNotification,
            'identifier' => $this->identifier,
            'identifierType' => $this->identifierType->value,
        ];

        if ($this->description !== null) { $data['description'] = $this->description; }
        if ($this->senderIban !== null) { $data['senderIban'] = $this->senderIban; }
        if ($this->psuId !== null) { $data['psuId'] = $this->psuId; }
        if ($this->paymentCategory !== null) { $data['paymentCategory'] = $this->paymentCategory; }

        return $data;
    }
}
