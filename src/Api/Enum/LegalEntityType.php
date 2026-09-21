<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Api\Enum;

enum LegalEntityType: string
{
    case SoleOwner = 'SOLEOWNER';
    case Ltd = 'LTD';
    case SolTd = 'SOLTD';
    case Jsc = 'JSC';
    case SoJsc = 'SOJSC';
    case Sd = 'SD';
    case Cd = 'CD';
    case Cda = 'CDA';
    case Dzzd = 'DZZD';
    case Npo = 'NPO';
    case Branch = 'BRANCH';
    case Not = 'NOT';
    case OtherProf = 'OTHERPROF';
    case Offshore = 'OFFSHORE';
    case OtherJrd = 'OTHER JRD';
    case Public = 'PUBLIC';
    case Vcc = 'VCC';
    case Other = 'OTHER';
}
