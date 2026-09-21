<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Api\Request;

final readonly class DirectPaymentData
{
    public function __construct(
        public string $bankHash,
        public string $receiverIban,
        public string $receiverName,
        public string $remittanceDescription,
        public float $sum,
        public string $currency,
        public string $hookHash,
        public bool $emailNotification = false,
        public ?string $description = null,
        public ?string $senderIban = null,
        public ?string $psuId = null,
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
        ];

        if ($this->description !== null) { $data['description'] = $this->description; }
        if ($this->senderIban !== null) { $data['senderIban'] = $this->senderIban; }
        if ($this->psuId !== null) { $data['psuId'] = $this->psuId; }

        return $data;
    }
}
