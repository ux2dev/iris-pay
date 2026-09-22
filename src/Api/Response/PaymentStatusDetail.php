<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Api\Response;

final readonly class PaymentStatusDetail
{
    public function __construct(
        public Payment $payment,
        public ?PaymentUrls $urlSca,
        public bool $ready,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            payment: Payment::fromArray($data['payment']),
            urlSca: isset($data['urlSca']) ? PaymentUrls::fromArray($data['urlSca']) : null,
            ready: (bool) ($data['ready'] ?? false),
        );
    }
}
