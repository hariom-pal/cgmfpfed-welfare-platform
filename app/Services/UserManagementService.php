<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Circle;
use App\Models\DistrictUnion;
use App\Models\Samiti;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Hash;

/**
 * Internal staff account management (Super Admin, District Union, Samiti, Investigation
 * Committee, Circle). VLE/CSC portal accounts are a separate, self-service identity system
 * (JIT-provisioned on CSC Connect login) and are intentionally excluded here, along with
 * Super Admin, matching the legacy CI3 "manageuser" screen which never lists or edits either.
 */
final class UserManagementService
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function query(array $filters = []): Builder
    {
        $query = User::query()
            ->whereNotIn('user_type', [1, (int) config('csc.vle_role_id')])
            ->with(['role', 'districtUnionMaster', 'samitiMaster', 'circleMaster']);

        if (filled($filters['name'] ?? null)) {
            $query->where('name', 'like', '%'.$filters['name'].'%');
        }

        if (filled($filters['email'] ?? null)) {
            $query->where('email', 'like', '%'.$filters['email'].'%');
        }

        if (filled($filters['user_type'] ?? null)) {
            $query->where('user_type', (int) $filters['user_type']);
        }

        if (filled($filters['status'] ?? null)) {
            $query->where('status', (string) $filters['status']);
        }

        return $query;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        return $this->query($filters)->orderBy('name')->paginate($perPage)->withQueryString();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $actor): User
    {
        $attributes = $this->geographyAttributes($data);
        $attributes['name'] = $data['name'];
        $attributes['email'] = $data['email'];
        $attributes['mobile'] = $data['mobile'];
        $attributes['user_type'] = (int) $data['user_type'];
        $attributes['status'] = (string) $data['status'];
        $attributes['password'] = Hash::make($data['password']);
        $attributes['reset_code'] = '1';
        $attributes['fail_attempt'] = 0;
        $attributes['add_date'] = now();

        return User::query()->create($attributes);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $target, array $data, User $actor): User
    {
        $attributes = $this->geographyAttributes($data + ['user_type' => $target->user_type]);
        $attributes['status'] = (string) $data['status'];

        if (filled($data['password'] ?? null)) {
            $attributes['password'] = Hash::make($data['password']);
            $attributes['reset_code'] = '1';
        }

        $target->forceFill($attributes)->save();

        return $target->refresh();
    }

    public function toggleStatus(User $target): User
    {
        $target->forceFill(['status' => $target->status === '1' ? '0' : '1'])->save();

        return $target->refresh();
    }

    /**
     * Reconciles the chosen normalized master records (District Union / Samiti / Circle)
     * onto both the modern FK columns and the legacy scalar columns (`districtunion`,
     * `samiti`, `circle`) that DataScopeService/PermissionService still read for row-level
     * visibility scoping — reusing that existing scoping mechanism rather than changing it.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function geographyAttributes(array $data): array
    {
        $userType = (int) ($data['user_type'] ?? 0);
        $attributes = [
            'district_union_master_id' => null,
            'districtunion' => 0,
            'samiti_master_id' => null,
            'samiti' => null,
            'circle_master_id' => null,
            'circle' => null,
        ];

        if (filled($data['district_union_id'] ?? null)) {
            $districtUnion = DistrictUnion::query()->find($data['district_union_id']);
            if ($districtUnion instanceof DistrictUnion) {
                $attributes['district_union_master_id'] = $districtUnion->id;
                $attributes['districtunion'] = $districtUnion->legacy_id ?? 0;
            }
        }

        if ($userType === 3 && filled($data['samiti_id'] ?? null)) {
            $samiti = Samiti::query()->find($data['samiti_id']);
            if ($samiti instanceof Samiti) {
                $attributes['samiti_master_id'] = $samiti->id;
                $attributes['samiti'] = $samiti->legacy_id;
            }
        }

        if ($userType === 5 && filled($data['circle_id'] ?? null)) {
            $circle = Circle::query()->find($data['circle_id']);
            if ($circle instanceof Circle) {
                $attributes['circle_master_id'] = $circle->id;
                $attributes['circle'] = $circle->legacy_id;
            }
        }

        return $attributes;
    }
}
