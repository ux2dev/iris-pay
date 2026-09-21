<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Resources;

use Ux2Dev\Iris\Api\Request\ConsentGateRequestData;
use Ux2Dev\Iris\Api\Response\ConsentGateResponse;

final class ConsentGate extends Resource
{
    public function createRequest(ConsentGateRequestData $data): ConsentGateResponse
    {
        $response = $this->transport->post('/api/cgate/consents-request', $data->toArray(), $this->credentials->adminAgent());

        return ConsentGateResponse::fromArray($response);
    }
}
