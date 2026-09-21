<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Laravel\Console;

use Illuminate\Console\Command;
use Ux2Dev\Iris\Laravel\IrisManager;

class StatusCheckCommand extends Command
{
    protected $signature = 'iris:status-check
                            {paymentHash : The payment hash to check}
                            {--merchant= : Merchant name (uses default if omitted)}';

    protected $description = 'Check the status of an IRIS payment by its hash';

    public function handle(IrisManager $manager): int
    {
        $paymentHash = $this->argument('paymentHash');
        $merchantName = $this->option('merchant');

        $client = $merchantName
            ? $manager->merchant($merchantName)->payByLink()
            : $manager->payByLink();

        try {
            $response = $client->getPaymentStatus($paymentHash);
        } catch (\Throwable $e) {
            $this->error("Failed to check status: {$e->getMessage()}");

            return self::FAILURE;
        }

        $this->table(
            ['Field', 'Value'],
            [
                ['Status', $response->status->value],
                ['Sum', (string) $response->sum],
                ['Currency', $response->currency],
                ['Date', $response->date],
                ['Description', $response->description],
                ['Order ID', $response->orderId ?? '-'],
                ['Payer Name', $response->payerName],
                ['Payer Bank', $response->payerBank],
                ['Payer IBAN', $response->payerIban],
                ['Receiver IBAN', $response->receiverIban],
            ],
        );

        return self::SUCCESS;
    }
}
