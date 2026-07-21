<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\Repositories\WorkflowStatusRepositoryInterface;
use App\Models\WorkflowStatus;

final class WorkflowStatusRepository extends MasterRepository implements WorkflowStatusRepositoryInterface
{
    public function __construct()
    {
        parent::__construct(new WorkflowStatus);
    }
}
