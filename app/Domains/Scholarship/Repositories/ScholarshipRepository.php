<?php

declare(strict_types=1);

namespace App\Domains\Scholarship\Repositories;

use App\Domains\Scholarship\Contracts\ScholarshipRepositoryInterface;
use App\Repositories\BaseRepository;

final class ScholarshipRepository extends BaseRepository implements ScholarshipRepositoryInterface
{
    public function save(): void {}

    public function findById(int $id): mixed
    {
        return null;
    }

    public function update(): void {}

    public function delete(): void {}
}
