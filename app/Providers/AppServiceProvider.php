<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\Services\AadhaarServiceInterface;
use App\Contracts\Services\CscConnectServiceInterface;
use App\Contracts\Services\DigiLockerServiceInterface;
use App\Contracts\Services\HealthCheckServiceInterface;
use App\Contracts\Services\TendupattaServiceInterface;
use App\Contracts\Services\WalletServiceInterface;
use App\Domains\Scholarship\Contracts\ScholarshipRepositoryInterface;
use App\Domains\Scholarship\Contracts\ScholarshipServiceInterface;
use App\Domains\Scholarship\Repositories\ScholarshipRepository;
use App\Domains\Scholarship\Services\ScholarshipService;
use App\Models\ScholarshipApplication;
use App\Policies\MasterPolicy;
use App\Policies\ScholarshipApplicationPolicy;
use App\Services\CscBridgeWalletService;
use App\Services\CscConnectService;
use App\Services\HealthCheckService;
use App\Services\MenuBuilder;
use App\Services\MockAadhaarService;
use App\Services\MockDigiLockerService;
use App\Services\MockTendupattaService;
use App\Services\PermissionService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
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

        $this->app->bind(AadhaarServiceInterface::class, MockAadhaarService::class);
        $this->app->bind(DigiLockerServiceInterface::class, MockDigiLockerService::class);
        $this->app->bind(TendupattaServiceInterface::class, MockTendupattaService::class);
        $this->app->bind(CscConnectServiceInterface::class, CscConnectService::class);
        $this->app->bind(WalletServiceInterface::class, CscBridgeWalletService::class);
        $this->app->bind(ScholarshipRepositoryInterface::class, ScholarshipRepository::class);
        $this->app->bind(ScholarshipServiceInterface::class, ScholarshipService::class);

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
        Gate::policy(ScholarshipApplication::class, ScholarshipApplicationPolicy::class);

        foreach (config('masters', []) as $master) {
            Gate::policy($master['model'], MasterPolicy::class);
        }

        foreach (array_keys(config('legacy_authorization.abilities', [])) as $ability) {
            Gate::define($ability, fn ($user): bool => app(PermissionService::class)->can($user, (string) $ability));
        }

        View::composer('components.sidebar', function ($view): void {
            $view->with('menuItems', app(MenuBuilder::class)->buildFor(auth()->user()));
        });
    }
}
