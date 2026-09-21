<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Api\Response;

use Ux2Dev\Iris\Api\Enum\PsuIdType;
use Ux2Dev\Iris\Api\Enum\ScaType;

final readonly class BankInfo
{
    /**
     * @param Video[] $videos
     */
    public function __construct(
        public string $bankHash,
        public string $name,
        public ?string $urlLogo,
        public ?string $urlDarkLogo,
        public ScaType $sca,
        public ?string $firstStepInstruction,
        public bool $directPayment,
        public bool $paymentRequiresIban,
        public ?string $fullName,
        public ?string $bic,
        public ?string $services,
        public ?string $country,
        public array $videos,
        public bool $consentRequiresIban,
        public bool $consentRequiresPsu,
        public bool $paymentRequiresAuthorization,
        public bool $consentRequiresAuthorization,
        public bool $paymentRequiresPsu,
        public PsuIdType $psuType,
        public bool $budgetPaymentsRequirePaymentCategory,
        public bool $aisAvailable,
        public bool $pisAvailable,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            bankHash: $data['bankHash'],
            name: $data['name'],
            urlLogo: $data['urlLogo'] ?? null,
            urlDarkLogo: $data['urlDarkLogo'] ?? null,
            sca: ScaType::from($data['sca']),
            firstStepInstruction: $data['firstStepInstruction'] ?? null,
            directPayment: (bool) ($data['directPayment'] ?? false),
            paymentRequiresIban: (bool) ($data['paymentRequiresIban'] ?? false),
            fullName: $data['fullName'] ?? null,
            bic: $data['bic'] ?? null,
            services: $data['services'] ?? null,
            country: $data['country'] ?? null,
            videos: array_map(fn (array $v) => Video::fromArray($v), $data['videos'] ?? []),
            consentRequiresIban: (bool) ($data['consentRequiresIban'] ?? false),
            consentRequiresPsu: (bool) ($data['consentRequiresPsu'] ?? false),
            paymentRequiresAuthorization: (bool) ($data['paymentRequiresAuthorization'] ?? false),
            consentRequiresAuthorization: (bool) ($data['consentRequiresAuthorization'] ?? false),
            paymentRequiresPsu: (bool) ($data['paymentRequiresPsu'] ?? false),
            psuType: PsuIdType::from($data['psuType']),
            budgetPaymentsRequirePaymentCategory: (bool) ($data['budgetPaymentsRequirePaymentCategory'] ?? false),
            aisAvailable: (bool) ($data['aisAvailable'] ?? false),
            pisAvailable: (bool) ($data['pisAvailable'] ?? false),
        );
    }
}
