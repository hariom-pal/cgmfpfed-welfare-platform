<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\Repositories\DistrictUnionRepositoryInterface;
use App\Models\DistrictUnion;

final class DistrictUnionRepository extends MasterRepository implements DistrictUnionRepositoryInterface
{
    public function __construct()
    {
        parent::__construct(new DistrictUnion);
    }
}
