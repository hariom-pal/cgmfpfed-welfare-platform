<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScholarshipTendupattaCollection extends Model
{
    protected $fillable = [
        'scholarship_application_id',
        'collection_year',
        'quantity_gaddi',
        'data_source',
        'is_verified',
        'verified_by',
        'verified_at',
        'remarks',
    ];

    public function application(): BelongsTo
    {
        return $this->belongsTo(ScholarshipApplication::class, 'scholarship_application_id');
    }

    protected function casts(): array
    {
        return [
            'quantity_gaddi' => 'decimal:2',
            'is_verified' => 'boolean',
            'verified_at' => 'datetime',
        ];
    }
}
