<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Laravel\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Ux2Dev\Iris\Webhook\WebhookPayload;

final readonly class WebhookReceived
{
    use Dispatchable;

    public function __construct(
        public WebhookPayload $payload,
        public ?string $merchant = null,
    ) {}
}
