<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Ux2Dev\Iris\Laravel\Http\Controllers\IrisWebhookController;

Route::group([
    'prefix' => config('iris.routes.prefix', 'iris'),
    'middleware' => config('iris.routes.middleware', ['web']),
], function () {
    Route::match(
        ['get', 'post'],
        '/webhook/{hashedOrderId}/{signature}',
        IrisWebhookController::class,
    )->name('iris.webhook');
});
