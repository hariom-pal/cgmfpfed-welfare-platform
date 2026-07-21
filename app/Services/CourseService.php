<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Repositories\CourseRepositoryInterface;
use App\Contracts\Services\CourseServiceInterface;

final class CourseService extends MasterService implements CourseServiceInterface
{
    public function __construct(CourseRepositoryInterface $repository)
    {
        parent::__construct($repository);
    }
}
