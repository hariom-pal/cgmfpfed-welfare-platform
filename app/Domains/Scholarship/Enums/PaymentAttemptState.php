<?php

declare(strict_types=1);

namespace App\Domains\Scholarship\Enums;

enum PaymentAttemptState: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Failed = 'failed';
}
