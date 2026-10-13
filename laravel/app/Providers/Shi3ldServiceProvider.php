<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class Shi3ldServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        config(['database.connections.magento_reporting' => [
            'driver' => 'mysql',
            'host' => env('MAGENTO_DB_HOST', 'db'),
            'port' => env('MAGENTO_DB_PORT', 3306),
            'database' => env('MAGENTO_DB_DATABASE', 'magento'),
            'username' => env('MAGENTO_DB_USERNAME', 'magento_read'),
            'password' => env('MAGENTO_DB_PASSWORD'),
            'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '', 'strict' => true,
        ]]);
    }
}
