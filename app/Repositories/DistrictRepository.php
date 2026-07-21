<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\Repositories\DistrictRepositoryInterface;
use App\Models\District;

final class DistrictRepository extends MasterRepository implements DistrictRepositoryInterface
{
    public function __construct()
    {
        parent::__construct(new District);
    }
}
