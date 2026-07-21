<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class RolePriviledge extends Model
{
    public $timestamps = false;

    protected $table = 'role_priviledge';

    protected $fillable = [
        'id',
        'role_id',
        'permission_id',
    ];
}
