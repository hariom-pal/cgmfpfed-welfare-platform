<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Repositories\WorkflowStatusRepositoryInterface;
use App\Contracts\Services\WorkflowStatusServiceInterface;

final class WorkflowStatusService extends MasterService implements WorkflowStatusServiceInterface
{
    public function __construct(WorkflowStatusRepositoryInterface $repository)
    {
        parent::__construct($repository);
    }
}
