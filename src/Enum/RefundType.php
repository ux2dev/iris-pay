<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Enum;

enum RefundType: string
{
    case Full = 'FULL';
    case Partial = 'PARTIAL';
}
