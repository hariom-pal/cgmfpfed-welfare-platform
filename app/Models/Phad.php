<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Phad extends BaseMasterModel
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'uuid',
        'legacy_id',
        'legacy_code',
        'code',
        'name',
        'district_id',
        'district_union_id',
        'samiti_id',
        'legacy_district_code',
        'legacy_district_union_id',
        'legacy_samiti_id',
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
        'district_union_id' => 'integer',
        'samiti_id' => 'integer',
        'legacy_district_union_id' => 'integer',
        'legacy_samiti_id' => 'integer',
        'is_active' => 'boolean',
    ];

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    public function districtUnion(): BelongsTo
    {
        return $this->belongsTo(DistrictUnion::class);
    }

    public function samiti(): BelongsTo
    {
        return $this->belongsTo(Samiti::class);
    }
}
