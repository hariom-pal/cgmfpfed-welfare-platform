<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Repositories\PhadRepositoryInterface;
use App\Contracts\Services\PhadServiceInterface;

final class PhadService extends MasterService implements PhadServiceInterface
{
    public function __construct(PhadRepositoryInterface $repository)
    {
        parent::__construct($repository);
    }
}
