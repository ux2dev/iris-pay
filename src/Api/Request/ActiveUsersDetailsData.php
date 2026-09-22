<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Api\Request;

final readonly class ActiveUsersDetailsData
{
    public function __construct(
        public string $agentHash,
        public string $fromDate,
        public string $toDate,
        public ?string $validUntil = null,
        public int $page = 0,
        public int $size = 30,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $data = [
            'agentHash' => $this->agentHash,
            'fromDate' => $this->fromDate,
            'toDate' => $this->toDate,
            'page' => $this->page,
            'size' => $this->size,
        ];

        if ($this->validUntil !== null) {
            $data['validUntil'] = $this->validUntil;
        }

        return $data;
    }
}
