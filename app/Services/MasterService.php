<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Repositories\MasterRepositoryInterface;
use App\Contracts\Services\MasterServiceInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;

class MasterService extends BaseService implements MasterServiceInterface
{
    public function __construct(protected MasterRepositoryInterface $repository) {}

    public function paginate(array $filters, string $sort, string $direction): LengthAwarePaginator
    {
        return $this->repository->paginate($filters, $sort, $direction);
    }

    public function findByUuid(string $uuid): Model
    {
        return $this->repository->findByUuid($uuid);
    }

    public function create(array $data): Model
    {
        return $this->repository->create($data);
    }

    public function update(Model $model, array $data): Model
    {
        return $this->repository->update($model, $data);
    }

    public function delete(Model $model): void
    {
        $this->repository->delete($model);
    }

    public function toggle(Model $model): Model
    {
        return $this->repository->toggle($model);
    }
}
