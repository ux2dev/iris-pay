<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Laravel\Facades;

use Illuminate\Support\Facades\Facade;
use Ux2Dev\Iris\Laravel\IrisManager;

/**
 * @method static IrisManager merchant(string $name)
 * @method static string currentMerchant()
 * @method static \Ux2Dev\Iris\Iris client()
 * @method static \Ux2Dev\Iris\Config\MerchantConfig getConfig()
 * @method static \Ux2Dev\Iris\UserScope user(string $userHash)
 * @method static \Ux2Dev\Iris\Resources\PayByLink payByLink()
 * @method static \Ux2Dev\Iris\Resources\Agent agent()
 * @method static \Ux2Dev\Iris\Resources\Accounts accounts()
 * @method static \Ux2Dev\Iris\Resources\Payments payments()
 * @method static \Ux2Dev\Iris\Resources\BulkPayments bulkPayments()
 * @method static \Ux2Dev\Iris\Resources\Reports reports()
 * @method static \Ux2Dev\Iris\Resources\ConsentGate consentGate()
 *
 * @see IrisManager
 */
final class Iris extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return IrisManager::class;
    }
}
