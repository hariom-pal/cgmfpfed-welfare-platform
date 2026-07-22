<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GramPanchayat extends BaseMasterModel
{
    protected $fillable = [
        'uuid',
        'legacy_code',
        'name',
        'block_id',
        'legacy_block_code',
        'is_active',
    ];

    protected $casts = [
        'block_id' => 'integer',
        'is_active' => 'boolean',
    ];

    public function block(): BelongsTo
    {
        return $this->belongsTo(Block::class);
    }

    public function villages(): HasMany
    {
        return $this->hasMany(Village::class);
    }
}
