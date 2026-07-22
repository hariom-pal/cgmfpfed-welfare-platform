<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Block extends BaseMasterModel
{
    protected $fillable = [
        'uuid',
        'legacy_code',
        'name',
        'district_id',
        'legacy_district_code',
        'is_active',
    ];

    protected $casts = [
        'district_id' => 'integer',
        'is_active' => 'boolean',
    ];

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    public function gramPanchayats(): HasMany
    {
        return $this->hasMany(GramPanchayat::class);
    }

    public function cities(): HasMany
    {
        return $this->hasMany(City::class);
    }
}
