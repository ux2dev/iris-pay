<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Api\Request;

use Ux2Dev\Iris\Enum\PaymentStatus;

final readonly class PaymentSearchData
{
    public function __construct(
        public int $page,
        public int $size,
        public ?PaymentStatus $status = null,
        public ?string $userHash = null,
        public ?string $agentHash = null,
        public ?string $fromDate = null,
        public ?string $toDate = null,
        public bool $noBulkPayments = false,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $data = [
            'page' => $this->page,
            'size' => $this->size,
            'noBulkPayments' => $this->noBulkPayments,
        ];

        if ($this->status !== null) { $data['status'] = $this->status->value; }
        if ($this->userHash !== null) { $data['userHash'] = $this->userHash; }
        if ($this->agentHash !== null) { $data['agentHash'] = $this->agentHash; }
        if ($this->fromDate !== null) { $data['fromDate'] = $this->fromDate; }
        if ($this->toDate !== null) { $data['toDate'] = $this->toDate; }

        return $data;
    }
}
