<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Api\Enum;

enum IdentifierType: string
{
    case Egn = 'EGN';
    case Eik = 'EIK';
    case Pnf = 'PNF';
}
