<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Api\Request;

final readonly class IbanPaymentData
{
    public function __construct(
        public int $fromIbanId,
        public string $receiverIban,
        public string $receiverName,
        public string $remittanceDescription,
        public float $sum,
        public string $currency,
        public string $hookHash,
        public bool $emailNotification = false,
        public ?string $description = null,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $data = [
            'fromIbanId' => $this->fromIbanId,
            'receiverIban' => $this->receiverIban,
            'receiverName' => $this->receiverName,
            'remittanceDescription' => $this->remittanceDescription,
            'sum' => $this->sum,
            'currency' => $this->currency,
            'hookHash' => $this->hookHash,
            'emailNotification' => $this->emailNotification,
        ];

        if ($this->description !== null) { $data['description'] = $this->description; }

        return $data;
    }
}
