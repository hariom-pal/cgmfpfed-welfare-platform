<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Repositories\RejectionReasonRepositoryInterface;
use App\Contracts\Services\RejectionReasonServiceInterface;

final class RejectionReasonService extends MasterService implements RejectionReasonServiceInterface
{
    public function __construct(RejectionReasonRepositoryInterface $repository)
    {
        parent::__construct($repository);
    }
}
