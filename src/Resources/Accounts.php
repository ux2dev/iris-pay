<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Resources;

use Ux2Dev\Iris\Api\Response\ConsentDetails;

final class Accounts extends Resource
{
    public function getConsentDetails(string $iban): ConsentDetails
    {
        $response = $this->transport->get("/api/8/consent/iban/{$iban}", $this->credentials->agent());

        return ConsentDetails::fromArray($response);
    }
}
