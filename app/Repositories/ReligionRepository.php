<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\Repositories\ReligionRepositoryInterface;
use App\Models\Religion;

final class ReligionRepository extends MasterRepository implements ReligionRepositoryInterface
{
    public function __construct()
    {
        parent::__construct(new Religion);
    }
}
