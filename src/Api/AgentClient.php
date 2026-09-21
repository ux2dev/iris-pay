<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Api;

use Ux2Dev\Iris\Api\Request\CreateHookData;
use Ux2Dev\Iris\Api\Request\SignupAgentData;
use Ux2Dev\Iris\Api\Request\SignupData;
use Ux2Dev\Iris\Api\Response\EmailAccount;
use Ux2Dev\Iris\Api\Response\Hook;
use Ux2Dev\Iris\Api\Response\IdentificationAccount;
use Ux2Dev\Iris\Api\Response\UsersList;

final class AgentClient extends BaseClient
{
    public function signup(SignupData $data): IdentificationAccount
    {
        $response = $this->postJson('/api/8/signup', $data->toArray());
        return IdentificationAccount::fromArray($response);
    }

    public function signupAgent(SignupAgentData $data): IdentificationAccount
    {
        $response = $this->postJson('/api/8/signup/agent', $data->toArray());
        return IdentificationAccount::fromArray($response);
    }

    public function createHook(CreateHookData $data): Hook
    {
        $response = $this->postJson('/api/8/createhook', $data->toArray());
        return Hook::fromArray($response);
    }

    public function createUserToken(string $userHash): string
    {
        return $this->postForString('/api/8/usertoken', $this->userHeaders($userHash));
    }

    public function deleteUser(string $userHash): void
    {
        $this->deleteVoid('/api/8/agent/user', $this->bothHeaders($userHash));
    }

    public function listUsers(
        int $page = 0,
        int $size = 30,
        ?string $dateFrom = null,
        ?string $dateTo = null,
        ?string $text = null,
    ): UsersList {
        $query = ['page' => $page, 'size' => $size];
        if ($dateFrom !== null) { $query['dateFrom'] = $dateFrom; }
        if ($dateTo !== null) { $query['dateTo'] = $dateTo; }
        if ($text !== null) { $query['text'] = $text; }

        $response = $this->getJson('/api/8/agent/users', $this->agentHeaders(), $query);
        return UsersList::fromArray($response);
    }

    public function checkUserByEmail(string $email): EmailAccount
    {
        $response = $this->postJson('/api/8/agent/user/check', ['email' => $email], $this->agentHeaders());
        return EmailAccount::fromArray($response);
    }

    public function sendAisEmail(string $userHash, string $hookHash, string $bankHash, string $email): void
    {
        $this->postVoid('/api/8/agent/ais/email', [
            'hookHash' => $hookHash,
            'bankHash' => $bankHash,
            'email' => $email,
        ], $this->bothHeaders($userHash));
    }

    public function addRedirectToHook(string $hookHash, string $redirectUrl): void
    {
        $this->putVoid('/api/8/redirect', [], ['hookhash' => $hookHash, 'redirectUrl' => $redirectUrl]);
    }

    public function getKycStatus(string $userHash): IdentificationAccount
    {
        $response = $this->getJson('/api/8/id/status', $this->userHeaders($userHash));
        return IdentificationAccount::fromArray($response);
    }

    public function updateKyc(string $submissionId, string $status): void
    {
        $this->postVoid('/api/8/kyc/update', [
            'submissionId' => $submissionId,
            'status' => $status,
        ], $this->agentHeaders());
    }
}
