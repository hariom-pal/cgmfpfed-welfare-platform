<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class UserType extends Model
{
    public $timestamps = false;

    protected $table = 'user_type';

    protected $fillable = [
        'id',
        'type',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'user_type');
    }
}
