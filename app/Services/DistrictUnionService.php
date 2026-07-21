<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Repositories\DistrictUnionRepositoryInterface;
use App\Contracts\Services\DistrictUnionServiceInterface;

final class DistrictUnionService extends MasterService implements DistrictUnionServiceInterface
{
    public function __construct(DistrictUnionRepositoryInterface $repository)
    {
        parent::__construct($repository);
    }
}
