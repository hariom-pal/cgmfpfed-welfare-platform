<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScholarshipPaymentAttempt extends Model
{
    protected $fillable = [
        'scholarship_application_id',
        'wallet_transaction_id',
        'payment_purpose',
        'payment_channel',
        'transaction_number',
        'amount',
        'payment_state',
        'payment_requested_at',
        'payment_completed_at',
        'failure_reason',
        'attempt_number',
        'request_payload',
        'response_payload',
        'created_by',
    ];

    public function application(): BelongsTo
    {
        return $this->belongsTo(ScholarshipApplication::class, 'scholarship_application_id');
    }

    public function walletTransaction(): BelongsTo
    {
        return $this->belongsTo(ScholarshipWalletTransaction::class, 'wallet_transaction_id');
    }

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'payment_requested_at' => 'datetime',
            'payment_completed_at' => 'datetime',
            'request_payload' => 'array',
            'response_payload' => 'array',
        ];
    }
}
