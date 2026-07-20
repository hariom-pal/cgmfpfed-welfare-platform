<?php

declare(strict_types=1);

namespace App\DTO;

final readonly class HealthCheckDTO extends BaseDTO
{
    public function __construct(
        public string $status,
        public string $application,
        public string $environment,
        public string $phpVersion,
        public string $laravelVersion,
        public string $timestamp,
    ) {}
}
