<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Api\Enum;

enum OptionalHookEvent: string
{
    case PaymentAuthorised = 'PAYMENT_AUTHORISED';
}
