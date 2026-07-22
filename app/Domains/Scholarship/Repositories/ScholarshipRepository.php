<?php

declare(strict_types=1);

namespace App\Domains\Scholarship\Repositories;

use App\Domains\Scholarship\Contracts\ScholarshipRepositoryInterface;
use App\Domains\Scholarship\Enums\ScholarshipApplicationStatus;
use App\Models\ScholarshipApplication;
use App\Models\User;
use App\Repositories\BaseRepository;
use App\Services\DataScopeService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

final class ScholarshipRepository extends BaseRepository implements ScholarshipRepositoryInterface
{
    public function __construct(
        private readonly DataScopeService $scope,
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
        return $this->filteredQueryFor($user, $filters)
            ->paginate($perPage)
            ->withQueryString();
    }

    public function filteredQueryFor(User $user, array $filters = []): Builder
    {
        $query = $this->queryVisibleFor($user);

        $query
            ->when($filters['scheme_id'] ?? null, fn (Builder $builder, mixed $schemeId) => $builder->where('scheme_id', $schemeId))
            ->when($filters['academic_session_id'] ?? null, fn (Builder $builder, mixed $sessionId) => $builder->where('academic_session_id', $sessionId))
            ->when($filters['district_union_id'] ?? null, fn (Builder $builder, mixed $districtUnionId) => $builder->where('district_union_id', $districtUnionId))
            ->when($filters['samiti_id'] ?? null, fn (Builder $builder, mixed $samitiId) => $builder->where('samiti_id', $samitiId))
            ->when($filters['phad_id'] ?? null, fn (Builder $builder, mixed $phadId) => $builder->where('phad_id', $phadId))
            ->when($filters['aadhaar_number'] ?? null, fn (Builder $builder, mixed $aadhaar) => $builder->where('student_aadhaar', $aadhaar))
            ->when($filters['application_number'] ?? null, fn (Builder $builder, mixed $applicationNumber) => $builder->where('application_number', 'like', "%{$applicationNumber}%"))
            ->when($filters['student_name'] ?? null, fn (Builder $builder, mixed $studentName) => $builder->where('student_name', 'like', "%{$studentName}%"))
            ->when($filters['last_action_from_date'] ?? null, function (Builder $builder, mixed $date): void {
                $this->whereLatestAction($builder, '>=', $date.' 00:00:00');
            })
            ->when($filters['last_action_to_date'] ?? null, function (Builder $builder, mixed $date): void {
                $this->whereLatestAction($builder, '<=', $date.' 23:59:59');
            })
            ->when($filters['last_action_role'] ?? null, function (Builder $builder, mixed $role): void {
                $builder->whereRaw(
                    '(select acted_by_role from scholarship_workflow_transitions where scholarship_workflow_transitions.scholarship_application_id = scholarship_applications.id order by acted_at desc, id desc limit 1) = ?',
                    [$role],
                );
            })
            ->when($filters['status'] ?? null, function (Builder $builder, mixed $status): void {
                match ($status) {
                    'pending' => $builder->whereIn('status', ScholarshipApplicationStatus::underProcessValues()),
                    'pending_vle' => $builder->whereIn('status', ScholarshipApplicationStatus::pendingAtVleValues()),
                    'rejected' => $builder->whereIn('status', ScholarshipApplicationStatus::rejectedValues()),
                    'completed' => $builder->whereIn('status', ScholarshipApplicationStatus::completedValues()),
                    'last_completed' => $builder->whereIn('status', ScholarshipApplicationStatus::completedValues())->reorder('completed_at', 'desc'),
                    default => null,
                };
            });

        return $query;
    }

    private function whereLatestAction(Builder $builder, string $operator, string $datetime): void
    {
        $builder->whereRaw(
            "(select max(acted_at) from scholarship_workflow_transitions where scholarship_workflow_transitions.scholarship_application_id = scholarship_applications.id) {$operator} ?",
            [$datetime],
        );
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
