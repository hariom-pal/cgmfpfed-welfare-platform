<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class District extends BaseMasterModel
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'uuid',
        'code',
        'legacy_code',
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

    public function districtUnions(): HasMany
    {
        return $this->hasMany(DistrictUnion::class);
    }

    public function samitis(): HasMany
    {
        return $this->hasMany(Samiti::class);
    }

    public function phads(): HasMany
    {
        return $this->hasMany(Phad::class);
    }

    public function blocks(): HasMany
    {
        return $this->hasMany(Block::class);
    }
}
