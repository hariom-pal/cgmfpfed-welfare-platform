<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Village extends BaseMasterModel
{
    protected $fillable = [
        'uuid',
        'legacy_code',
        'name',
        'gram_panchayat_id',
        'legacy_gram_panchayat_code',
        'is_active',
    ];

    protected $casts = [
        'gram_panchayat_id' => 'integer',
        'is_active' => 'boolean',
    ];

    public function gramPanchayat(): BelongsTo
    {
        return $this->belongsTo(GramPanchayat::class);
    }
}
