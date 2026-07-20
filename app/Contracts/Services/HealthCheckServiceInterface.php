<?php

declare(strict_types=1);

namespace App\Contracts\Services;

use App\DTO\HealthCheckDTO;

interface HealthCheckServiceInterface
{
    /**
     * Check the overall health of the application.
     */
    public function check(): HealthCheckDTO;
}
