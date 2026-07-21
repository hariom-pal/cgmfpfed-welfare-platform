<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\Repositories\CategoryRepositoryInterface;
use App\Models\Category;

final class CategoryRepository extends MasterRepository implements CategoryRepositoryInterface
{
    public function __construct()
    {
        parent::__construct(new Category);
    }
}
