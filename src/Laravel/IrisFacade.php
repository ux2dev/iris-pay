<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Laravel;

use Illuminate\Support\Facades\Facade;

/**
 * @method static IrisManager merchant(string $name)
 * @method static \Ux2Dev\Iris\PayByLink\PayByLinkClient payByLink()
 * @method static \Ux2Dev\Iris\Api\AgentClient agent()
 * @method static \Ux2Dev\Iris\Api\AccountClient account()
 * @method static \Ux2Dev\Iris\Api\PaymentClient payment()
 * @method static \Ux2Dev\Iris\Api\BulkPaymentClient bulkPayment()
 * @method static \Ux2Dev\Iris\Api\ReportClient report()
 * @method static \Ux2Dev\Iris\Api\ConsentGateClient consentGate()
 * @method static \Ux2Dev\Iris\Config\MerchantConfig getConfig()
 *
 * @see \Ux2Dev\Iris\Laravel\IrisManager
 */
class IrisFacade extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return IrisManager::class;
    }
}
