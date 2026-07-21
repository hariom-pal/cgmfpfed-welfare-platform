<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

interface MasterRepositoryInterface
{
    /**
     * @return LengthAwarePaginator<int, Model>
     */
    public function paginate(array $filters = [], string $sort = 'name', string $direction = 'asc', int $perPage = 15): LengthAwarePaginator;

    public function findByUuid(string $uuid): Model;

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Model;

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Model $model, array $data): Model;

    public function delete(Model $model): void;

    public function toggle(Model $model): Model;

    /**
     * @return Collection<int, Model>
     */
    public function active(): Collection;
}
