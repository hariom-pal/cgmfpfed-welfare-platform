<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\Repositories\PhadRepositoryInterface;
use App\Models\Phad;

final class PhadRepository extends MasterRepository implements PhadRepositoryInterface
{
    public function __construct()
    {
        parent::__construct(new Phad);
    }
}
