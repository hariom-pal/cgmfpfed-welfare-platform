<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScholarshipApplicationAudit extends Model
{
    protected $fillable = [
        'scholarship_application_id',
        'from_status',
        'to_status',
        'action',
        'stage',
        'remarks',
        'acted_by',
        'acted_at',
        'payload',
    ];

    public function application(): BelongsTo
    {
        return $this->belongsTo(ScholarshipApplication::class, 'scholarship_application_id');
    }

    protected function casts(): array
    {
        return [
            'from_status' => 'integer',
            'to_status' => 'integer',
            'acted_at' => 'datetime',
            'payload' => 'array',
        ];
    }
}
