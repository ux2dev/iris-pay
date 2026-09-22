<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Api\Request;

final readonly class BulkBudgetIbanData
{
    /**
     * @param BulkBudgetEntry[] $payments
     */
    public function __construct(
        public string $currency,
        public string $hookHash,
        public bool $emailNotification,
        public int $fromIbanId,
        public ?string $requestedExecutionDate,
        public array $payments,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $data = [
            'currency' => $this->currency,
            'hookHash' => $this->hookHash,
            'emailNotification' => $this->emailNotification,
            'fromIbanId' => $this->fromIbanId,
            'payments' => array_map(fn (BulkBudgetEntry $entry) => $entry->toArray(), $this->payments),
        ];

        if ($this->requestedExecutionDate !== null) { $data['requestedExecutionDate'] = $this->requestedExecutionDate; }

        return $data;
    }
}
