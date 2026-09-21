<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Api\Request;

final readonly class ConsentGateRequestData
{
    /**
     * @param array{firstName: string, middleName: string, lastName: string, email: string, phone: string, uic: string} $userInfo
     * @param string[] $ibans
     */
    public function __construct(
        public array $userInfo,
        public array $ibans,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'userInfo' => $this->userInfo,
            'ibans' => $this->ibans,
        ];
    }
}
