<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Resources;

use Ux2Dev\Iris\Api\Request\CreateHookData;
use Ux2Dev\Iris\Api\Request\SignupAgentData;
use Ux2Dev\Iris\Api\Request\SignupData;
use Ux2Dev\Iris\Api\Response\EmailAccount;
use Ux2Dev\Iris\Api\Response\Hook;
use Ux2Dev\Iris\Api\Response\IdentificationAccount;
use Ux2Dev\Iris\Api\Response\UsersList;

final class Agent extends Resource
{
    public function signup(SignupData $data): IdentificationAccount
    {
        $response = $this->transport->post('/api/8/signup', $data->toArray());

        return IdentificationAccount::fromArray($response);
    }

    public function signupAgent(SignupAgentData $data): IdentificationAccount
    {
        $response = $this->transport->post('/api/8/signup/agent', $data->toArray());

        return IdentificationAccount::fromArray($response);
    }

    public function createHook(CreateHookData $data): Hook
    {
        $response = $this->transport->post('/api/8/createhook', $data->toArray());

        return Hook::fromArray($response);
    }

    public function addRedirectToHook(string $hookHash, string $redirectUrl): void
    {
        $this->transport->putVoid('/api/8/redirect', [], [], ['hookhash' => $hookHash, 'redirectUrl' => $redirectUrl]);
    }

    public function listUsers(
        int $page = 0,
        int $size = 30,
        ?string $dateFrom = null,
        ?string $dateTo = null,
        ?string $text = null,
    ): UsersList {
        $query = ['page' => $page, 'size' => $size];
        if ($dateFrom !== null) {
            $query['dateFrom'] = $dateFrom;
        }
        if ($dateTo !== null) {
            $query['dateTo'] = $dateTo;
        }
        if ($text !== null) {
            $query['text'] = $text;
        }

        $response = $this->transport->get('/api/8/agent/users', $this->credentials->agent(), $query);

        return UsersList::fromArray($response);
    }

    public function checkUserByEmail(string $email): EmailAccount
    {
        $response = $this->transport->post('/api/8/agent/user/check', ['email' => $email], $this->credentials->agent());

        return EmailAccount::fromArray($response);
    }

    public function updateKyc(string $submissionId, string $status): void
    {
        $this->transport->postVoid('/api/8/kyc/update', [
            'submissionId' => $submissionId,
            'status' => $status,
        ], $this->credentials->agent());
    }
}
