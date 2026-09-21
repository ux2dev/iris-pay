<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Enum;

enum BankScaType: string
{
    case RedirectUrl = 'REDIRECT_URL';
    case CodeRedirectUrl = 'CODE_REDIRECT_URL';
    case Push = 'PUSH';
}
