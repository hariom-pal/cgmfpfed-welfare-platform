<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Repositories\ReligionRepositoryInterface;
use App\Contracts\Services\ReligionServiceInterface;

final class ReligionService extends MasterService implements ReligionServiceInterface
{
    public function __construct(ReligionRepositoryInterface $repository)
    {
        parent::__construct($repository);
    }
}
