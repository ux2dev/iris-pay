<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Api\Enum;

enum ConsentErrorCode: string
{
    case ConsentExpired = 'CONSENT_EXPIRED';
    case BalanceFailed = 'BALANCE_FAILED';
    case TransactionsFailed = 'TRANSACTIONS_FAILED';
    case ConsentDetailsFailed = 'CONSENT_DETAILS_FAILED';
}
