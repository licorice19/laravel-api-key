<?php

namespace Licorice19\ApiKey\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Licorice19\ApiKey\ApiKeyServiceProvider;

abstract class TestCase extends Orchestra
{
    /**
     * Setting up the environment before each test.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Выполняем миграции пакета
        $this->loadMigrationsFrom(__DIR__ . '/../src/database/migrations');
    }

    /**
     * Get package service providers.
     *
     * @param \Illuminate\Foundation\Application $app
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            ApiKeyServiceProvider::class,
        ];
    }

    /**
     * Setting up the environment.
     *
     * @param \Illuminate\Foundation\Application $app
     */
    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $app['config']->set('cache.default', 'array');
    }
}