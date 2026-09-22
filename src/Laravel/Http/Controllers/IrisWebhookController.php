<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Laravel\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Ux2Dev\Iris\Enum\PaymentStatus;
use Ux2Dev\Iris\Laravel\Events\PaymentConfirmed;
use Ux2Dev\Iris\Laravel\Events\PaymentFailed;
use Ux2Dev\Iris\Laravel\Events\WebhookReceived;
use Ux2Dev\Iris\Webhook\WebhookParser;

class IrisWebhookController extends Controller
{
    public function __invoke(Request $request, string $hashedOrderId, string $signature): RedirectResponse
    {
        $this->verifySignature($hashedOrderId, $signature);

        $payload = WebhookParser::payload($request->query(), $hashedOrderId);
        $merchant = config('iris.default');

        WebhookReceived::dispatch($payload, $merchant);

        match ($payload->status) {
            PaymentStatus::Confirmed => PaymentConfirmed::dispatch($payload->status, $merchant),
            PaymentStatus::Failed => PaymentFailed::dispatch($payload->status, $merchant),
            default => null,
        };

        $redirectPath = match ($payload->status) {
            PaymentStatus::Confirmed => config('iris.redirect.success', '/payment/success'),
            default => config('iris.redirect.failure', '/payment/failure'),
        };

        return redirect($redirectPath);
    }

    private function verifySignature(string $hashedOrderId, string $signature): void
    {
        $secret = config('iris.webhook.secret');

        if ($secret === null || $secret === '') {
            throw new AccessDeniedHttpException('Webhook secret is not configured');
        }

        $expected = hash_hmac('sha256', $hashedOrderId, (string) $secret);

        if (!hash_equals($expected, $signature)) {
            throw new AccessDeniedHttpException('Invalid webhook signature');
        }
    }
}
