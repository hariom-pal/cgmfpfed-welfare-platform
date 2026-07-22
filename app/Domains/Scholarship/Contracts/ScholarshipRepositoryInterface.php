<?php

declare(strict_types=1);

namespace App\Domains\Scholarship\Contracts;

use App\Models\ScholarshipApplication;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

interface ScholarshipRepositoryInterface
{
    public function queryVisibleFor(User $user): Builder;

    /**
     * @param  array<string, mixed>  $filters
     */
    public function filteredQueryFor(User $user, array $filters = []): Builder;

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginateFor(User $user, array $filters = [], int $perPage = 15): LengthAwarePaginator;

    public function findVisible(int $id, User $user): ScholarshipApplication;

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): ScholarshipApplication;

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(ScholarshipApplication $application, array $data): ScholarshipApplication;
}
