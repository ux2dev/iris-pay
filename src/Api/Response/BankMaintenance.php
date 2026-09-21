<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Api\Response;

final readonly class BankMaintenance
{
    public function __construct(
        public string $bankName,
        public string $maintenanceMessage,
        public string $fromDate,
        public string $toDate,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            bankName: $data['bankName'],
            maintenanceMessage: $data['maintenanceMessage'],
            fromDate: $data['fromDate'],
            toDate: $data['toDate'],
        );
    }
}
