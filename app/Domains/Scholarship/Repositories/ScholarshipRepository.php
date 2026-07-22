<?php

declare(strict_types=1);

namespace App\Domains\Scholarship\Repositories;

use App\Domains\Scholarship\Contracts\ScholarshipRepositoryInterface;
use App\Models\ScholarshipApplication;
use App\Models\User;
use App\Repositories\BaseRepository;
use App\Services\DataScopeService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

final class ScholarshipRepository extends BaseRepository implements ScholarshipRepositoryInterface
{
    public function __construct(private readonly DataScopeService $scope) {}

    public function queryVisibleFor(User $user): Builder
    {
        $query = ScholarshipApplication::query()
            ->with(['academicSession', 'scheme', 'applicant', 'district', 'districtUnion', 'samiti', 'phad'])
            ->latest();

        return $this->scope->applyScholarshipVisibility($query, $user);
    }

    public function paginateFor(User $user, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->queryVisibleFor($user);

        $query
            ->when($filters['status'] ?? null, fn (Builder $builder, mixed $status) => $builder->where('status', $status))
            ->when($filters['scheme_id'] ?? null, fn (Builder $builder, mixed $schemeId) => $builder->where('scheme_id', $schemeId))
            ->when($filters['academic_session_id'] ?? null, fn (Builder $builder, mixed $sessionId) => $builder->where('academic_session_id', $sessionId))
            ->when($filters['student_aadhaar'] ?? null, fn (Builder $builder, mixed $aadhaar) => $builder->where('student_aadhaar', $aadhaar))
            ->when($filters['q'] ?? null, function (Builder $builder, mixed $search): void {
                $builder->where(function (Builder $nested) use ($search): void {
                    $nested
                        ->where('student_name', 'like', "%{$search}%")
                        ->orWhere('application_number', 'like', "%{$search}%")
                        ->orWhere('student_aadhaar', 'like', "%{$search}%");
                });
            });

        return $query->paginate($perPage)->withQueryString();
    }

    public function findVisible(int $id, User $user): ScholarshipApplication
    {
        $application = $this->queryVisibleFor($user)->findOrFail($id);

        if (! $application instanceof ScholarshipApplication) {
            abort(404);
        }

        return $application;
    }

    public function create(array $data): ScholarshipApplication
    {
        return ScholarshipApplication::query()->create($data);
    }

    public function update(ScholarshipApplication $application, array $data): ScholarshipApplication
    {
        $application->fill($data)->save();

        return $application->refresh();
    }
}
