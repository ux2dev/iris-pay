<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Api\Enum;

enum TokenType: string
{
    case AuthAis = 'AUTH_AIS';
    case AuthPis = 'AUTH_PIS';
    case RefreshAis = 'REFRESH_AIS';
    case RefreshPis = 'REFRESH_PIS';
    case ConsentReceived = 'CONSENT_RECEIVED';
    case ConsentValid = 'CONSENT_VALID';
}
