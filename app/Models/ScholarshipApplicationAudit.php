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

    /**
     * @return BelongsTo<ScholarshipApplication, $this>
     */
    public function application(): BelongsTo
    {
        return $this->belongsTo(ScholarshipApplication::class, 'scholarship_application_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acted_by');
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
