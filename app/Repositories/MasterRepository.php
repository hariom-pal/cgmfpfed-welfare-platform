<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\Repositories\MasterRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class MasterRepository extends BaseRepository implements MasterRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $master
     */
    public function __construct(protected Model $model, private readonly array $master = []) {}

    /**
     * @return LengthAwarePaginator<int, Model>
     */
    public function paginate(array $filters = [], string $sort = 'name', string $direction = 'asc', int $perPage = 15): LengthAwarePaginator
    {
        $table = $this->model->getTable();
        $allowedSorts = array_values(array_filter(
            $this->master['sort_columns'] ?? ['code', 'name', 'is_active', 'created_at'],
            fn (string $column): bool => Schema::hasColumn($table, $column),
        ));
        $sort = in_array($sort, $allowedSorts, true) ? $sort : 'name';
        if (! Schema::hasColumn($table, $sort)) {
            $sort = $allowedSorts[0] ?? $this->model->getKeyName();
        }
        $direction = $direction === 'desc' ? 'desc' : 'asc';
        $searchColumns = array_values(array_filter(
            $this->master['search_columns'] ?? ['code', 'name', 'description'],
            fn (string $column): bool => Schema::hasColumn($table, $column),
        ));

        return $this->model
            ->newQuery()
            ->when(($filters['search'] ?? null) && $searchColumns !== [], function ($query) use ($filters, $searchColumns): void {
                $query->where(function ($query) use ($filters, $searchColumns): void {
                    foreach ($searchColumns as $index => $column) {
                        $method = $index === 0 ? 'where' : 'orWhere';
                        $query->{$method}($column, 'like', '%'.$filters['search'].'%');
                    }
                });
            })
            ->when(array_key_exists('is_active', $filters) && $filters['is_active'] !== null, function ($query) use ($filters): void {
                $query->where('is_active', (bool) $filters['is_active']);
            })
            ->orderBy($sort, $direction)
            ->paginate($perPage)
            ->withQueryString();
    }

    public function findByUuid(string $uuid): Model
    {
        return $this->model->newQuery()->where('uuid', $uuid)->firstOrFail();
    }

    public function create(array $data): Model
    {
        $data['uuid'] ??= (string) Str::uuid();

        return $this->model->newQuery()->create($data);
    }

    public function update(Model $model, array $data): Model
    {
        $model->update($data);

        return $model->refresh();
    }

    public function delete(Model $model): void
    {
        $model->delete();
    }

    public function toggle(Model $model): Model
    {
        $model->update(['is_active' => ! (bool) $model->getAttribute('is_active')]);

        return $model->refresh();
    }

    public function active(): Collection
    {
        return $this->model->newQuery()->where('is_active', true)->orderBy('name')->get();
    }

    public function table(): string
    {
        return $this->model->getTable();
    }
}
