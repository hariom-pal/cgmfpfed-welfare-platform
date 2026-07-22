<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class Circle extends BaseMasterModel
{
    protected $fillable = [
        'uuid',
        'legacy_id',
        'legacy_code',
        'name',
        'is_active',
    ];

    protected $casts = [
        'legacy_id' => 'integer',
        'is_active' => 'boolean',
    ];

    public function districtUnions(): HasMany
    {
        return $this->hasMany(DistrictUnion::class);
    }
}
