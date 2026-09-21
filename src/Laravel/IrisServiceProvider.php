<?php

declare(strict_types=1);

namespace Ux2Dev\Iris\Laravel;

use Illuminate\Support\ServiceProvider;

class IrisServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/config/iris.php', 'iris');

        $this->app->singleton(IrisManager::class, function ($app) {
            return new IrisManager($app['config']->get('iris'));
        });

        $this->app->alias(IrisManager::class, 'iris');
    }

    public function boot(): void
    {
        if (class_exists(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class)) {
            $this->app->resolving(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class, function ($middleware) {
                $prefix = $this->app['config']->get('iris.routes.prefix', 'iris');
                $middleware->except(["{$prefix}/webhook"]);
            });
        }

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/config/iris.php' => config_path('iris.php'),
            ], 'iris-config');

            $this->publishes([
                __DIR__ . '/routes/iris.php' => base_path('routes/iris.php'),
            ], 'iris-routes');

            $this->commands([
                Console\StatusCheckCommand::class,
            ]);
        }

        if ($this->app['config']->get('iris.routes.enabled', true)) {
            $this->loadRoutesFrom(__DIR__ . '/routes/iris.php');
        }
    }
}
