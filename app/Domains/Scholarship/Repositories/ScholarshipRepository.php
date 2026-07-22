<?php

declare(strict_types=1);

namespace App\Domains\Scholarship\Repositories;

use App\Domains\Scholarship\Contracts\ScholarshipRepositoryInterface;
use App\Models\ScholarshipApplication;
use App\Models\User;
use App\Repositories\BaseRepository;
use App\Services\ApplicationCategoryService;
use App\Services\DataScopeService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

final class ScholarshipRepository extends BaseRepository implements ScholarshipRepositoryInterface
{
    public function __construct(
        private readonly DataScopeService $scope,
        private readonly ApplicationCategoryService $categories,
    ) {}

    public function queryVisibleFor(User $user): Builder
    {
        $query = ScholarshipApplication::query()
            ->with([
                'academicSession',
                'scholarshipSession',
                'scheme',
                'applicant',
                'district',
                'districtUnion',
                'samiti',
                'phad',
                'block',
                'gramPanchayat',
                'village',
                'city',
                'ward',
                'latestWorkflowTransition',
            ])
            ->latest();

        return $this->scope->applyScholarshipVisibility($query, $user);
    }

    public function paginateFor(User $user, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->queryVisibleFor($user);

        $query
            ->when($filters['status'] ?? null, fn (Builder $builder, mixed $status) => $builder->where('status', $status))
            ->when($filters['current_status'] ?? null, fn (Builder $builder, mixed $status) => $builder->where('status', $status))
            ->when($filters['workflow_stage'] ?? null, fn (Builder $builder, mixed $stage) => $builder->where('workflow_stage', $stage))
            ->when($filters['scheme_id'] ?? null, fn (Builder $builder, mixed $schemeId) => $builder->where('scheme_id', $schemeId))
            ->when($filters['academic_session_id'] ?? null, fn (Builder $builder, mixed $sessionId) => $builder->where('academic_session_id', $sessionId))
            ->when($filters['scholarship_session_id'] ?? null, fn (Builder $builder, mixed $sessionId) => $builder->where('scholarship_session_id', $sessionId))
            ->when($filters['student_aadhaar'] ?? null, fn (Builder $builder, mixed $aadhaar) => $builder->where('student_aadhaar', $aadhaar))
            ->when($filters['aadhaar_number'] ?? null, fn (Builder $builder, mixed $aadhaar) => $builder->where('student_aadhaar', $aadhaar))
            ->when($filters['application_id'] ?? null, function (Builder $builder, mixed $applicationId): void {
                $builder->where(function (Builder $nested) use ($applicationId): void {
                    $nested
                        ->where('id', $applicationId)
                        ->orWhere('application_number', 'like', "%{$applicationId}%")
                        ->orWhere('legacy_application_id', $applicationId);
                });
            })
            ->when($filters['last_action_from_date'] ?? null, function (Builder $builder, mixed $date): void {
                $builder->whereRaw(
                    '(select max(acted_at) from scholarship_workflow_transitions where scholarship_workflow_transitions.scholarship_application_id = scholarship_applications.id) >= ?',
                    [$date.' 00:00:00'],
                );
            })
            ->when($filters['last_action_to_date'] ?? null, function (Builder $builder, mixed $date): void {
                $builder->whereRaw(
                    '(select max(acted_at) from scholarship_workflow_transitions where scholarship_workflow_transitions.scholarship_application_id = scholarship_applications.id) <= ?',
                    [$date.' 23:59:59'],
                );
            })
            ->when($filters['q'] ?? null, function (Builder $builder, mixed $search): void {
                $builder->where(function (Builder $nested) use ($search): void {
                    $nested
                        ->where('student_name', 'like', "%{$search}%")
                        ->orWhere('application_number', 'like', "%{$search}%")
                        ->orWhere('student_aadhaar', 'like', "%{$search}%");
                });
            });

        $this->categories->apply($query, $filters['category'] ?? null);

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
