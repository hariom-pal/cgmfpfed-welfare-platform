<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\Repositories\RejectionReasonRepositoryInterface;
use App\Models\RejectionReason;

final class RejectionReasonRepository extends MasterRepository implements RejectionReasonRepositoryInterface
{
    public function __construct()
    {
        parent::__construct(new RejectionReason);
    }
}
