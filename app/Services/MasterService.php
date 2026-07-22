<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Repositories\MasterRepositoryInterface;
use App\Contracts\Services\MasterServiceInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

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
        $this->stampActor($data, 'created_by');
        $this->stampActor($data, 'updated_by');

        return $this->repository->create($data);
    }

    public function update(Model $model, array $data): Model
    {
        $this->stampActor($data, 'updated_by', $model);

        return $this->repository->update($model, $data);
    }

    public function delete(Model $model): void
    {
        if (auth()->id() !== null && Schema::hasColumn($model->getTable(), 'deleted_by')) {
            $model->setAttribute('deleted_by', auth()->id());
            $model->save();
        }

        $this->repository->delete($model);
    }

    public function toggle(Model $model): Model
    {
        if (auth()->id() !== null && Schema::hasColumn($model->getTable(), 'updated_by')) {
            $model->setAttribute('updated_by', auth()->id());
            $model->save();
        }

        return $this->repository->toggle($model);
    }

    private function stampActor(array &$data, string $column, ?Model $model = null): void
    {
        $table = $model?->getTable() ?? (method_exists($this->repository, 'table') ? $this->repository->table() : null);
        if ($table === null || auth()->id() === null || ! Schema::hasColumn($table, $column)) {
            return;
        }

        $data[$column] = auth()->id();
    }
}
