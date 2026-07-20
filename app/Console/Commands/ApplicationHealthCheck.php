<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\Services\HealthCheckServiceInterface;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:health')]
#[Description('Check the overall health of the application')]
class ApplicationHealthCheck extends Command
{
    public function __construct(
        private readonly HealthCheckServiceInterface $healthCheckService,
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $health = $this->healthCheckService->check();

        $this->info($health->application);
        $this->line(str_repeat('-', 32));
        $this->info('Status      : '.strtoupper($health->status));
        $this->line('Environment : '.$health->environment);
        $this->line('PHP Version : '.$health->phpVersion);
        $this->line('Laravel     : '.$health->laravelVersion);
        $this->line('Timestamp   : '.$health->timestamp);

        return self::SUCCESS;
    }
}
