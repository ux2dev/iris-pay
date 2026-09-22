<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Api\Request;

final readonly class CreateConsentData
{
    public function __construct(
        public string $bankHash,
        public ?string $username = null,
        public ?string $iban = null,
        public ?string $hookHash = null,
        public ?string $sms = null,
        public ?string $authorizationUrl = null,
        public ?string $authorizationId = null,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return array_filter([
            'bankHash' => $this->bankHash,
            'username' => $this->username,
            'iban' => $this->iban,
            'hookHash' => $this->hookHash,
            'sms' => $this->sms,
            'authorizationUrl' => $this->authorizationUrl,
            'authorizationId' => $this->authorizationId,
        ], fn ($v) => $v !== null);
    }
}
