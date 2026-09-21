<?php

declare(strict_types=1);

use Ux2Dev\Iris\Resources\Accounts;
use Ux2Dev\Iris\Resources\Agent;
use Ux2Dev\Iris\Resources\BulkPayments;
use Ux2Dev\Iris\Resources\ConsentGate;
use Ux2Dev\Iris\Resources\PayByLink;
use Ux2Dev\Iris\Resources\Payments;
use Ux2Dev\Iris\Resources\Reports;
use Ux2Dev\Iris\Resources\User\Accounts as UserAccounts;
use Ux2Dev\Iris\Resources\User\Agent as UserAgent;
use Ux2Dev\Iris\Resources\User\BulkPayments as UserBulkPayments;
use Ux2Dev\Iris\Resources\User\ConsentGate as UserConsentGate;
use Ux2Dev\Iris\Resources\User\Payments as UserPayments;
use Ux2Dev\Iris\Resources\User\Reports as UserReports;

return [
    // --- Root: PayByLink (6) ---
    [PayByLink::class, 'getBanks', 0],
    [PayByLink::class, 'createLink', 5],
    [PayByLink::class, 'getQrCode', 1],
    [PayByLink::class, 'getStatus', 1],
    [PayByLink::class, 'refund', 5],
    [PayByLink::class, 'deactivate', 1],

    // --- Root: Agent (7) ---
    [Agent::class, 'signup', 1],
    [Agent::class, 'signupAgent', 1],
    [Agent::class, 'createHook', 1],
    [Agent::class, 'addRedirectToHook', 2],
    [Agent::class, 'listUsers', 0],
    [Agent::class, 'checkUserByEmail', 1],
    [Agent::class, 'updateKyc', 2],

    // --- Root: Accounts (1) ---
    [Accounts::class, 'getConsentDetails', 1],

    // --- Root: Payments (1) ---
    [Payments::class, 'statusByHook', 1],

    // --- Root: BulkPayments (2) ---
    [BulkPayments::class, 'status', 1],
    [BulkPayments::class, 'search', 1],

    // --- Root: Reports (4) ---
    [Reports::class, 'searchPayments', 1],
    [Reports::class, 'activeUsers', 2],
    [Reports::class, 'activeUsersDetails', 1],
    [Reports::class, 'bankMaintenance', 0],

    // --- Root: ConsentGate (2) ---
    [ConsentGate::class, 'createRequest', 1],
    [ConsentGate::class, 'getConsents', 0],

    // --- User: Accounts (12) ---
    [UserAccounts::class, 'listBanks', 0],
    [UserAccounts::class, 'getBank', 1],
    [UserAccounts::class, 'getBankSca', 1],
    [UserAccounts::class, 'listIbans', 0],
    [UserAccounts::class, 'deleteIban', 1],
    [UserAccounts::class, 'getBalance', 1],
    [UserAccounts::class, 'listTransactions', 1],
    [UserAccounts::class, 'listPagedTransactions', 1],
    [UserAccounts::class, 'getTransaction', 2],
    [UserAccounts::class, 'listTokens', 0],
    [UserAccounts::class, 'createConsent', 1],
    [UserAccounts::class, 'getConsents', 1],

    // --- User: Payments (11) ---
    [UserPayments::class, 'createDirect', 1],
    [UserPayments::class, 'createDirectInitiate', 1],
    [UserPayments::class, 'createIban', 1],
    [UserPayments::class, 'confirm', 2],
    [UserPayments::class, 'confirmWithResult', 2],
    [UserPayments::class, 'confirmSms', 3],
    [UserPayments::class, 'statusByCode', 1],
    [UserPayments::class, 'authorization', 1],
    [UserPayments::class, 'sca', 1],
    [UserPayments::class, 'createBudget', 1],
    [UserPayments::class, 'createBudgetDirect', 1],

    // --- User: BulkPayments (4) ---
    [UserBulkPayments::class, 'create', 1],
    [UserBulkPayments::class, 'createIban', 1],
    [UserBulkPayments::class, 'createBudget', 1],
    [UserBulkPayments::class, 'createBudgetIban', 1],

    // --- User: Agent (4) ---
    [UserAgent::class, 'createToken', 0],
    [UserAgent::class, 'delete', 0],
    [UserAgent::class, 'sendAisEmail', 3],
    [UserAgent::class, 'kycStatus', 0],

    // --- User: Reports (1) ---
    [UserReports::class, 'listPayments', 0],

    // --- User: ConsentGate (1) ---
    [UserConsentGate::class, 'uiConsentRequest', 0],
];
