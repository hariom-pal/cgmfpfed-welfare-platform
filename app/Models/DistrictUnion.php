<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DistrictUnion extends BaseMasterModel
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'uuid',
        'legacy_id',
        'code',
        'name',
        'district_id',
        'circle_id',
        'legacy_district_code',
        'legacy_circle_id',
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
        'legacy_id' => 'integer',
        'district_id' => 'integer',
        'circle_id' => 'integer',
        'legacy_circle_id' => 'integer',
        'is_active' => 'boolean',
    ];

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    public function circle(): BelongsTo
    {
        return $this->belongsTo(Circle::class);
    }

    public function samitis(): HasMany
    {
        return $this->hasMany(Samiti::class);
    }

    public function phads(): HasMany
    {
        return $this->hasMany(Phad::class);
    }
}
