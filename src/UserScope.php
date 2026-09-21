<?php

declare(strict_types=1);

namespace Ux2Dev\Iris;

use Ux2Dev\Iris\Http\Credentials;
use Ux2Dev\Iris\Http\IrisTransport;
use Ux2Dev\Iris\Resources\User\Accounts;
use Ux2Dev\Iris\Resources\User\Agent;
use Ux2Dev\Iris\Resources\User\BulkPayments;
use Ux2Dev\Iris\Resources\User\ConsentGate;
use Ux2Dev\Iris\Resources\User\Payments;
use Ux2Dev\Iris\Resources\User\Reports;

/**
 * Every IRIS endpoint that concerns one end user. Holding the hash here is
 * what keeps it out of 33 method signatures.
 */
final class UserScope
{
    private ?Accounts $accounts = null;
    private ?Payments $payments = null;
    private ?BulkPayments $bulkPayments = null;
    private ?Agent $agent = null;
    private ?Reports $reports = null;
    private ?ConsentGate $consentGate = null;

    public function __construct(
        private readonly IrisTransport $transport,
        private readonly Credentials $credentials,
        private readonly string $userHash,
    ) {
        // Validates the hash once for every user-scoped resource, reusing
        // Credentials::user() rather than duplicating its regex here.
        $this->credentials->user($this->userHash);
    }

    public function userHash(): string
    {
        return $this->userHash;
    }

    public function accounts(): Accounts
    {
        return $this->accounts ??= new Accounts($this->transport, $this->credentials, $this->userHash);
    }

    public function payments(): Payments
    {
        return $this->payments ??= new Payments($this->transport, $this->credentials, $this->userHash);
    }

    public function bulkPayments(): BulkPayments
    {
        return $this->bulkPayments ??= new BulkPayments($this->transport, $this->credentials, $this->userHash);
    }

    public function agent(): Agent
    {
        return $this->agent ??= new Agent($this->transport, $this->credentials, $this->userHash);
    }

    public function reports(): Reports
    {
        return $this->reports ??= new Reports($this->transport, $this->credentials, $this->userHash);
    }

    public function consentGate(): ConsentGate
    {
        return $this->consentGate ??= new ConsentGate($this->transport, $this->credentials, $this->userHash);
    }
}
