<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\Services\HealthCheckServiceInterface;
use App\Services\HealthCheckService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(
            HealthCheckServiceInterface::class,
            HealthCheckService::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
