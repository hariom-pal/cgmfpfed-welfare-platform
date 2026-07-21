<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScholarshipNotification extends Model
{
    protected $fillable = [
        'scholarship_application_id',
        'user_id',
        'channel',
        'subject',
        'body',
        'status',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
        ];
    }
}
