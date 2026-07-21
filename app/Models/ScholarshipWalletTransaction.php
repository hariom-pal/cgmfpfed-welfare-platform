<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScholarshipWalletTransaction extends Model
{
    protected $fillable = [
        'scholarship_application_id',
        'user_id',
        'transaction_type',
        'amount',
        'reference',
        'status',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'metadata' => 'array',
        ];
    }
}
