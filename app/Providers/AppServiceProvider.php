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

        foreach (config('masters', []) as $master) {
            $class = class_basename($master['model']);

            $this->app->bind(
                "App\\Contracts\\Repositories\\{$class}RepositoryInterface",
                "App\\Repositories\\{$class}Repository",
            );

            $this->app->bind(
                "App\\Contracts\\Services\\{$class}ServiceInterface",
                "App\\Services\\{$class}Service",
            );
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
