<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Api\Request;

final readonly class BulkPaymentData
{
    /**
     * @param BulkEntry[] $payments
     */
    public function __construct(
        public string $bankHash,
        public string $currency,
        public string $hookHash,
        public bool $emailNotification,
        public string $senderIban,
        public ?string $psuId,
        public ?string $requestedExecutionDate,
        public array $payments,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $data = [
            'bankHash' => $this->bankHash,
            'currency' => $this->currency,
            'hookHash' => $this->hookHash,
            'emailNotification' => $this->emailNotification,
            'senderIban' => $this->senderIban,
            'payments' => array_map(fn (BulkEntry $entry) => $entry->toArray(), $this->payments),
        ];

        if ($this->psuId !== null) { $data['psuId'] = $this->psuId; }
        if ($this->requestedExecutionDate !== null) { $data['requestedExecutionDate'] = $this->requestedExecutionDate; }

        return $data;
    }
}
