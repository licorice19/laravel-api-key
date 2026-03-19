<?php

namespace Licorice19\ApiKey;

use Illuminate\Support\ServiceProvider;
use Licorice19\ApiKey\Services\ApiKeyService;
use Licorice19\ApiKey\Http\Middlewares\ApiKeyMiddleware;
use Licorice19\ApiKey\Http\Middlewares\ApiKeyTagMiddleware;
use Licorice19\ApiKey\Console\Commands\CreateApiKey;
use Licorice19\ApiKey\Console\Commands\ListApiKeys;
use Licorice19\ApiKey\Console\Commands\RevokeApiKey;
use Licorice19\ApiKey\Console\Commands\ActivateApiKey;
use Licorice19\ApiKey\Console\Commands\DeleteApiKey;

class ApiKeyServiceProvider extends ServiceProvider
{
    /**
     * Register services provided by the package.
     */
    public function register(): void
    {
        $this->app->singleton(ApiKeyService::class, function () {
            return new ApiKeyService();
        });

        $this->app->alias(ApiKeyService::class, 'api-key');

        $this->mergeConfigFrom(
            __DIR__ . '/config/api-key.php',
            'api-key'
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/config/api-key.php' => config_path('api-key.php'),
        ], 'api-key-config');

        $this->publishes([
            __DIR__ . '/database/migrations' => database_path('migrations'),
        ], 'api-key-migrations');

        $this->loadMigrationsFrom(__DIR__ . '/database/migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([
                CreateApiKey::class,
                ListApiKeys::class,
                RevokeApiKey::class,
                ActivateApiKey::class,
                DeleteApiKey::class,
            ]);
        }

        $this->app['router']->aliasMiddleware('api-key', ApiKeyMiddleware::class);
        $this->app['router']->aliasMiddleware('api-key.tag', ApiKeyTagMiddleware::class);
    }
}