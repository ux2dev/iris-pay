<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Api\Request;

final readonly class SignupData
{
    public function __construct(
        public string $agentHash,
        public string $companyName,
        public string $uic,
        public string $name,
        public string $middleName,
        public string $family,
        public string $identityHash,
        public string $email,
        public ?string $webhookUrl = null,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $data = [
            'agentHash' => $this->agentHash,
            'companyName' => $this->companyName,
            'uic' => $this->uic,
            'name' => $this->name,
            'middleName' => $this->middleName,
            'family' => $this->family,
            'identityHash' => $this->identityHash,
            'email' => $this->email,
            'webhookUrl' => $this->webhookUrl,
        ];

        return array_filter($data, static fn (mixed $value): bool => $value !== null);
    }
}
