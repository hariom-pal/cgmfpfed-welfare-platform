<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
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
        'password',
        'status',
        'add_date',
        'user_type',
        'district',
        'circle',
        'districtunion',
        'samiti',
        'reset_code',
        'fail_attempt',
    ];

    public function role(): BelongsTo
    {
        return $this->belongsTo(UserType::class, 'user_type');
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
        if ($this->user_type === null) {
            return false;
        }

        return RolePriviledge::query()
            ->where('role_id', $this->user_type)
            ->where('permission_id', (int) $permission)
            ->exists();
    }

    /**
     * @param  iterable<int|string>|int|string  $permissions
     */
    public function hasAnyPermission(iterable|int|string $permissions): bool
    {
        $permissionIds = is_iterable($permissions) ? $permissions : [$permissions];

        foreach ($permissionIds as $permission) {
            if ($this->hasPermission($permission)) {
                return true;
            }
        }

        return false;
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
            'districtunion' => 'integer',
            'samiti' => 'integer',
            'fail_attempt' => 'integer',
        ];
    }
}
