<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Resources\User;

use Ux2Dev\Iris\Http\Credentials;
use Ux2Dev\Iris\Http\IrisTransport;

abstract class UserResource
{
    public function __construct(
        protected readonly IrisTransport $transport,
        protected readonly Credentials $credentials,
        protected readonly string $userHash,
    ) {
    }
}
