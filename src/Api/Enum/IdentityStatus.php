<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Api\Enum;

enum IdentityStatus: string
{
    case Success = 'SUCCESS';
    case Pending = 'PENDING';
    case Fail = 'FAIL';
    case NotStarted = 'NOT_STARTED';
}
