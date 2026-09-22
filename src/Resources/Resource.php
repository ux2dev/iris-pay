<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Resources;

use Ux2Dev\Iris\Http\Credentials;
use Ux2Dev\Iris\Http\IrisTransport;

abstract class Resource
{
    public function __construct(
        protected readonly IrisTransport $transport,
        protected readonly Credentials $credentials,
    ) {
    }
}
