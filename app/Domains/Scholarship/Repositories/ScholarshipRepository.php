<?php

declare(strict_types=1);

namespace App\Domains\Scholarship\Repositories;

use App\Domains\Scholarship\Contracts\ScholarshipRepositoryInterface;
use App\Domains\Scholarship\Enums\ScholarshipApplicationStatus;
use App\Models\ScholarshipApplication;
use App\Models\User;
use App\Repositories\BaseRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

final class ScholarshipRepository extends BaseRepository implements ScholarshipRepositoryInterface
{
    public function queryVisibleFor(User $user): Builder
    {
        $query = ScholarshipApplication::query()
            ->with(['academicSession', 'scheme', 'applicant', 'district', 'districtUnion', 'samiti', 'phad'])
            ->latest();

        return match ((int) $user->user_type) {
            (int) config('csc.vle_role_id') => $query->where('applicant_user_id', $user->id),
            2, 4 => $query->whereIn('district_union_id', $this->districtUnionScope($user)),
            3 => $query->where('samiti_id', (int) $user->samiti),
            5 => $query->whereIn('district_union_id', $this->circleDistrictUnionScope($user)),
            6 => $query->whereIn('status', [
                ScholarshipApplicationStatus::RecommendedForPayment->value,
                ScholarshipApplicationStatus::RecommendedForPaymentViaCCF->value,
                ScholarshipApplicationStatus::FinalApplicationForPayment->value,
                ScholarshipApplicationStatus::PaymentBatchSubmitted->value,
                ScholarshipApplicationStatus::PaymentFailed->value,
                ScholarshipApplicationStatus::PaymentFailedViaCCF->value,
            ]),
            default => $query,
        };
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

    /**
     * @return list<int>
     */
    private function districtUnionScope(User $user): array
    {
        $districtUnion = (int) $user->districtunion;

        return in_array($districtUnion, [5, 32], true) ? [5, 32] : [$districtUnion];
    }

    /**
     * @return list<int>
     */
    private function circleDistrictUnionScope(User $user): array
    {
        $districtUnionIds = (array) DB::table('district_unions')
            ->where('description', 'like', '%, circle ID: '.((int) $user->circle))
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();

        if (array_intersect($districtUnionIds, [5, 32]) !== []) {
            $districtUnionIds = array_values(array_unique([...$districtUnionIds, 5, 32]));
        }

        return $districtUnionIds;
    }
}
