<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class Priviledge extends Model
{
    public $timestamps = false;

    protected $table = 'priviledge';

    protected $fillable = [
        'id',
        'priviledge_name',
    ];
}
