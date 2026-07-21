<?php

declare(strict_types=1);

namespace App\Contracts\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;

interface MasterServiceInterface
{
    public function paginate(array $filters, string $sort, string $direction): LengthAwarePaginator;

    public function findByUuid(string $uuid): Model;

    public function create(array $data): Model;

    public function update(Model $model, array $data): Model;

    public function delete(Model $model): void;

    public function toggle(Model $model): Model;
}
