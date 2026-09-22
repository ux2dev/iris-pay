<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Api\Request;

use Ux2Dev\Iris\Api\Enum\LegalEntityType;

final readonly class SignupAgentData
{
    public function __construct(
        public string $agentHash,
        public string $companyName,
        public string $uic,
        public string $name,
        public string $middleName,
        public string $family,
        public string $email,
        public bool $requiresPublicHash = false,
        public ?string $publicHash = null,
        public ?string $webhookUrl = null,
        public ?LegalEntityType $legalEntityType = null,
        public ?string $mobile = null,
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
            'email' => $this->email,
            'requiresPublicHash' => $this->requiresPublicHash,
        ];

        if ($this->publicHash !== null) {
            $data['publicHash'] = $this->publicHash;
        }

        if ($this->webhookUrl !== null) {
            $data['webhookUrl'] = $this->webhookUrl;
        }

        if ($this->legalEntityType !== null) {
            $data['legalEntityType'] = $this->legalEntityType->value;
        }

        if ($this->mobile !== null) {
            $data['mobile'] = $this->mobile;
        }

        return $data;
    }
}
