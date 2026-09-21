<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Api\Enum;

enum PsuIdType: string
{
    case Username = 'USERNAME';
    case Msisdn = 'MSISDN';
    case UsernameAndClientNumber = 'USERNAME_AND_CLIENT_NUMBER';
    case ZbRetail = 'ZB_RETAIL';
    case OibHr = 'OIB_HR';
    case TokenSn = 'TOKEN_SN';
}
