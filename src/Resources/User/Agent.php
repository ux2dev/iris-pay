<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Resources\User;

use Ux2Dev\Iris\Api\Response\IdentificationAccount;

final class Agent extends UserResource
{
    public function createToken(): string
    {
        return $this->transport->postForString('/api/8/usertoken', $this->credentials->user($this->userHash));
    }

    public function delete(): void
    {
        $this->transport->delete('/api/8/agent/user', $this->credentials->both($this->userHash));
    }

    public function sendAisEmail(string $hookHash, string $bankHash, string $email): void
    {
        $this->transport->postVoid('/api/8/agent/ais/email', [
            'hookHash' => $hookHash,
            'bankHash' => $bankHash,
            'email' => $email,
        ], $this->credentials->both($this->userHash));
    }

    public function kycStatus(): IdentificationAccount
    {
        $data = $this->transport->get('/api/8/id/status', $this->credentials->user($this->userHash));

        return IdentificationAccount::fromArray($data);
    }
}
