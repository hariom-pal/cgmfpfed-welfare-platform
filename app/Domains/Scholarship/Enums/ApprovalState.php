<?php

declare(strict_types=1);

namespace App\Domains\Scholarship\Enums;

enum ApprovalState: string
{
    case Pending = 'pending';
    case Recommended = 'recommended';
    case ReturnedForCorrection = 'returned_for_correction';
    case Rejected = 'rejected';
    case Approved = 'approved';
}
