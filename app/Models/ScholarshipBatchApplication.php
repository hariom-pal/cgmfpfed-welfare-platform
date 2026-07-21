<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScholarshipBatchApplication extends Model
{
    protected $fillable = [
        'scholarship_workflow_batch_id',
        'scholarship_application_id',
        'amount',
        'payment_status',
        'payment_failure_reason',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ScholarshipWorkflowBatch::class, 'scholarship_workflow_batch_id');
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(ScholarshipApplication::class, 'scholarship_application_id');
    }

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }
}
