<?php

declare(strict_types=1);

namespace App\Services;

use App\Domains\Scholarship\Enums\ScholarshipApplicationStatus;
use App\Models\ScholarshipApplication;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class DataScopeService
{
    public function __construct(private readonly RoleService $roles) {}

    /**
     * @param  Builder<ScholarshipApplication>  $query
     * @return Builder<ScholarshipApplication>
     */
    public function applyScholarshipVisibility(Builder $query, User $user): Builder
    {
        return match ($this->roles->key($user)) {
            'VLE' => $query->where('applicant_user_id', $user->id),
            2, 4 => $query->whereIn('district_union_id', $this->districtUnionScope($user)),
            3 => $query->where('samiti_id', (int) $user->samiti),
            5 => $query->whereIn('district_union_id', $this->circleDistrictUnionScope($user)),
            6 => $query->whereIn('status', $this->financeStatuses()),
            default => $query,
        };
    }

    public function canViewScholarshipApplication(User $user, ScholarshipApplication $application): bool
    {
        return match ($this->roles->key($user)) {
            'VLE' => (int) $application->applicant_user_id === (int) $user->id,
            2, 4 => in_array((int) $application->district_union_id, $this->districtUnionScope($user), true),
            3 => (int) $application->district_union_id === (int) $user->districtunion
                && (int) $application->samiti_id === (int) $user->samiti,
            5 => in_array((int) $application->district_union_id, $this->circleDistrictUnionScope($user), true),
            6 => in_array((int) $application->status, $this->financeStatuses(), true),
            default => true,
        };
    }

    /**
     * @return list<int>
     */
    public function districtUnionScope(User $user): array
    {
        $districtUnion = (int) $user->districtunion;

        return in_array($districtUnion, [5, 32], true) ? [5, 32] : array_values(array_filter([$districtUnion]));
    }

    /**
     * @return list<int>
     */
    public function circleDistrictUnionScope(User $user): array
    {
        $circleId = (int) $user->circle;
        if ($circleId <= 0) {
            return [];
        }

        $ids = $this->districtUnionIdsFromArchive($circleId);

        if ($ids === [] && Schema::hasTable('district_unions') && Schema::hasColumn('district_unions', 'circle_id')) {
            $ids = DB::table('district_unions')
                ->where('circle_id', $circleId)
                ->pluck('id')
                ->map(fn (mixed $id): int => (int) $id)
                ->all();
        }

        if ($ids === [] && Schema::hasTable('district_unions')) {
            $ids = DB::table('district_unions')
                ->where('description', 'like', '%, circle ID: '.$circleId)
                ->pluck('id')
                ->map(fn (mixed $id): int => (int) $id)
                ->all();
        }

        if (array_intersect($ids, [5, 32]) !== []) {
            $ids = array_values(array_unique([...$ids, 5, 32]));
        }

        return $ids;
    }

    /**
     * @return list<int>
     */
    private function districtUnionIdsFromArchive(int $circleId): array
    {
        if (! Schema::hasTable('source_data_archives')) {
            return [];
        }

        return DB::table('source_data_archives')
            ->where('source_table', 'district_union')
            ->get()
            ->filter(function (object $row) use ($circleId): bool {
                $payload = (array) json_decode((string) $row->payload, true);

                return (int) ($payload['circle_id'] ?? 0) === $circleId;
            })
            ->map(function (object $row): int {
                $payload = (array) json_decode((string) $row->payload, true);

                return (int) ($payload['id'] ?? 0);
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return list<int>
     */
    private function financeStatuses(): array
    {
        return [
            ScholarshipApplicationStatus::RecommendedForPayment->value,
            ScholarshipApplicationStatus::RecommendedForPaymentViaCCF->value,
            ScholarshipApplicationStatus::FinalApplicationForPayment->value,
            ScholarshipApplicationStatus::PaymentBatchSubmitted->value,
            ScholarshipApplicationStatus::PaymentFailed->value,
            ScholarshipApplicationStatus::PaymentFailedViaCCF->value,
        ];
    }
}
