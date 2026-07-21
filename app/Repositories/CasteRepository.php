<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\Repositories\CasteRepositoryInterface;
use App\Models\Caste;

final class CasteRepository extends MasterRepository implements CasteRepositoryInterface
{
    public function __construct()
    {
        parent::__construct(new Caste);
    }
}
