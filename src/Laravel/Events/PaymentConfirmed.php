<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Laravel\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Ux2Dev\Iris\Enum\PaymentStatus;

final readonly class PaymentConfirmed
{
    use Dispatchable;

    public function __construct(
        public PaymentStatus $status,
        public ?string $merchant = null,
    ) {}
}
