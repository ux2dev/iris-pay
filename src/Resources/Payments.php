<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Resources;

use Ux2Dev\Iris\Api\Response\Payment;

final class Payments extends Resource
{
    public function statusByHook(string $hookHash): Payment
    {
        $response = $this->transport->get("/api/8/status/{$hookHash}", $this->credentials->agent());

        return Payment::fromArray($response);
    }
}
