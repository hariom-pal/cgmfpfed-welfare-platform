<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Ward extends BaseMasterModel
{
    protected $fillable = [
        'uuid',
        'legacy_code',
        'name',
        'city_id',
        'legacy_city_code',
        'is_active',
    ];

    protected $casts = [
        'city_id' => 'integer',
        'is_active' => 'boolean',
    ];

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }
}
