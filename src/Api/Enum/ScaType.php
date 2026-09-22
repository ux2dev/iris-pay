<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Api\Enum;

enum ScaType: string
{
    case RedirectUrl = 'REDIRECT_URL';
    case Push = 'PUSH';
    case CodeRedirectUrl = 'CODE_REDIRECT_URL';
    case OauthRedirectUrl = 'OAUTH_REDIRECT_URL';
}
