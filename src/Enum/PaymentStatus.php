<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Enum;

enum PaymentStatus: string
{
    case Waiting = 'WAITING';
    case Confirmed = 'CONFIRMED';
    case Failed = 'FAILED';
    case Rejected = 'REJECTED';
    case ScaRedirected = 'SCA_REDIRECTED';
    case Created = 'CREATED';
    case BulkProcessed = 'BULK_PROCESSED';
    case PaymentNotStarted = 'PAYMENT_NOT_STARTED';
}
