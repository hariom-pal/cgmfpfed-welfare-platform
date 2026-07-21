<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Repositories\SchemeRepositoryInterface;
use App\Contracts\Services\SchemeServiceInterface;

final class SchemeService extends MasterService implements SchemeServiceInterface
{
    public function __construct(SchemeRepositoryInterface $repository)
    {
        parent::__construct($repository);
    }
}
