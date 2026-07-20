<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Services\HealthCheckServiceInterface;
use App\DTO\HealthCheckDTO;

class HealthCheckService extends BaseService implements HealthCheckServiceInterface
{
    /**
     * Check the overall health of the application.
     */
    public function check(): HealthCheckDTO
    {
        return new HealthCheckDTO(
            status: 'healthy',
            application: config('app.name'),
            environment: app()->environment(),
            phpVersion: PHP_VERSION,
            laravelVersion: app()->version(),
            timestamp: now()->toDateTimeString(),
        );
    }
}
