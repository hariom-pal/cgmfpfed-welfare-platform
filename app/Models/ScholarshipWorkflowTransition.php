<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScholarshipWorkflowTransition extends Model
{
    protected $fillable = [
        'scholarship_application_id',
        'from_application_state',
        'to_application_state',
        'from_workflow_state',
        'to_workflow_state',
        'from_workflow_stage',
        'to_workflow_stage',
        'from_payment_state',
        'to_payment_state',
        'from_approval_state',
        'to_approval_state',
        'action',
        'remarks',
        'acted_by',
        'acted_by_role',
        'acted_at',
        'payload',
    ];

    public function application(): BelongsTo
    {
        return $this->belongsTo(ScholarshipApplication::class, 'scholarship_application_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acted_by');
    }

    protected function casts(): array
    {
        return [
            'acted_at' => 'datetime',
            'payload' => 'array',
        ];
    }
}
