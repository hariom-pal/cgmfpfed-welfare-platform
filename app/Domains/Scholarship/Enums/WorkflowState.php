<?php

declare(strict_types=1);

namespace App\Domains\Scholarship\Enums;

enum WorkflowState: string
{
    case PendingAtVle = 'pending_at_vle';
    case PendingSamiti = 'pending_samiti';
    case ResubmittedPendingSamiti = 'resubmitted_pending_samiti';
    case PendingIc = 'pending_ic';
    case PendingDistrictUnion = 'pending_district_union';
    case PendingHq = 'pending_hq';
    case PendingAccounts = 'pending_accounts';
    case ReturnedForCorrection = 'returned_for_correction';
    case Rejected = 'rejected';
    case AccountDetailsUpdated = 'account_details_updated';
    case PaymentProcessing = 'payment_processing';
    case PaymentFailed = 'payment_failed';
    case PaymentCompleted = 'payment_completed';
    case Appealed = 'appealed';
    case SourceSystemReview = 'source_system_review';
}
