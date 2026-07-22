<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Services\PermissionService;
use App\Services\RoleService;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'mobile',
        'csc_id',
        'csc_payload',
        'password',
        'status',
        'add_date',
        'user_type',
        'district',
        'circle',
        'circle_master_id',
        'districtunion',
        'district_union_master_id',
        'samiti',
        'samiti_master_id',
        'reset_code',
        'fail_attempt',
    ];

    public function role(): BelongsTo
    {
        return $this->belongsTo(UserType::class, 'user_type');
    }

    public function districtUnionMaster(): BelongsTo
    {
        return $this->belongsTo(DistrictUnion::class, 'district_union_master_id');
    }

    public function samitiMaster(): BelongsTo
    {
        return $this->belongsTo(Samiti::class, 'samiti_master_id');
    }

    public function circleMaster(): BelongsTo
    {
        return $this->belongsTo(Circle::class, 'circle_master_id');
    }

    public function isActive(): bool
    {
        return $this->status === '1';
    }

    /**
     * @param  int|string  $permission
     */
    public function hasPermission($permission): bool
    {
        return app(PermissionService::class)->has($this, $permission);
    }

    /**
     * @param  iterable<int|string>|int|string  $permissions
     */
    public function hasAnyPermission(iterable|int|string $permissions): bool
    {
        return app(PermissionService::class)->hasAny($this, $permissions);
    }

    public function canLegacy(string $ability): bool
    {
        return app(PermissionService::class)->can($this, $ability);
    }

    public function isVle(): bool
    {
        return app(RoleService::class)->isVle($this);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'add_date' => 'datetime',
            'user_type' => 'integer',
            'district' => 'integer',
            'circle' => 'integer',
            'circle_master_id' => 'integer',
            'districtunion' => 'integer',
            'district_union_master_id' => 'integer',
            'samiti' => 'integer',
            'samiti_master_id' => 'integer',
            'fail_attempt' => 'integer',
            'csc_payload' => 'array',
        ];
    }
}
