<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\Repositories\SamitiRepositoryInterface;
use App\Models\Samiti;

final class SamitiRepository extends MasterRepository implements SamitiRepositoryInterface
{
    public function __construct()
    {
        parent::__construct(new Samiti);
    }
}
