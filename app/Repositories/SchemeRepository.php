<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\Repositories\SchemeRepositoryInterface;
use App\Models\Scheme;

final class SchemeRepository extends MasterRepository implements SchemeRepositoryInterface
{
    public function __construct()
    {
        parent::__construct(new Scheme);
    }
}
