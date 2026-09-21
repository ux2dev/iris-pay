<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Resources\User;

use Ux2Dev\Iris\Api\Response\ConsentGateStatus;
use Ux2Dev\Iris\Api\Response\ConsentGateUi;

final class ConsentGate extends UserResource
{
    public function uiConsentRequest(): ConsentGateUi
    {
        $data = $this->transport->get('/api/cgate/ui/consents-request', $this->credentials->user($this->userHash));

        return ConsentGateUi::fromArray($data);
    }

    /** @return ConsentGateStatus[] */
    public function getConsents(bool $fulfilled = true): array
    {
        $data = $this->transport->get(
            "/api/cgate/consents-request/{$this->userHash}",
            $this->credentials->admin(),
            ['fulfilled' => $fulfilled ? 'true' : 'false'],
        );

        return array_map(fn (array $item) => ConsentGateStatus::fromArray($item), $data);
    }
}
