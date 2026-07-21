<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Repositories\DistrictRepositoryInterface;
use App\Contracts\Services\DistrictServiceInterface;

final class DistrictService extends MasterService implements DistrictServiceInterface
{
    public function __construct(DistrictRepositoryInterface $repository)
    {
        parent::__construct($repository);
    }
}
