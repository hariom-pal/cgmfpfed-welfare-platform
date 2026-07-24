<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ScholarshipWorkflowBatch extends Model
{
    protected $fillable = [
        'uuid',
        'batch_number',
        'type',
        'status',
        'meeting_date',
        'financial_year',
        'mom_file_path',
        'axis_file_path',
        'axis_file_generated_at',
        'remarks',
        'total_applications',
        'total_amount',
        'created_by',
        'submitted_at',
        'finalized_at',
    ];

    public function applications(): HasMany
    {
        return $this->hasMany(ScholarshipBatchApplication::class);
    }

    protected function casts(): array
    {
        return [
            'meeting_date' => 'date',
            'total_applications' => 'integer',
            'total_amount' => 'decimal:2',
            'submitted_at' => 'datetime',
            'finalized_at' => 'datetime',
            'axis_file_generated_at' => 'datetime',
        ];
    }
}
