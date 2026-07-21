<?php

declare(strict_types=1);

namespace App\Models;

class Religion extends BaseMasterModel
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'uuid',
        'code',
        'name',
        'description',
        'is_active',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
    ];
}
