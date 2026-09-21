<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Api\Enum;

enum CreditDebitIndicator: string
{
    case Credit = 'CREDIT';
    case Debit = 'DEBIT';
}
