<?php

declare(strict_types=1);

namespace App\Domains\Scholarship\Enums;

enum SubmissionState: string
{
    case Draft = 'draft';
    case WalletPending = 'wallet_pending';
    case Submitted = 'submitted';
    case Resubmitted = 'resubmitted';
}
