<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\Repositories\CourseRepositoryInterface;
use App\Models\Course;

final class CourseRepository extends MasterRepository implements CourseRepositoryInterface
{
    public function __construct()
    {
        parent::__construct(new Course);
    }
}
