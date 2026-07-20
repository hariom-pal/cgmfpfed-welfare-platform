<?php

declare(strict_types=1);

namespace App\Domains\Scholarship\Enums;

enum ScholarshipApplicationStatus: int
{
    /**
     * Active Workflow
     */
    case Pending = 0;
    case Resubmitted = 1;

    case RejectedBySamiti = 2;
    case RejectedByIC = 3;

    case RecommendedBySamiti = 4;
    case RecommendedByIC = 5;

    case AppealedByBeneficiary = 6;

    /**
     * Legacy CCF Workflow
     *
     * These statuses are retained only for compatibility with
     * migrated applications. New applications must never enter
     * these states.
     */
    case RejectedByCCF = 7;
    case RecommendedByCCF = 8;

    case RejectedByDistrictUnion = 9;
    case RejectedByDistrictUnionViaCCF = 10;

    case RecommendedByDistrictUnion = 11;
    case RecommendedByDistrictUnionViaCCF = 12;

    case RejectedByHQ = 13;
    case RejectedByHQViaCCF = 14;

    case RecommendedForPayment = 15;
    case RecommendedForPaymentViaCCF = 16;

    case PaymentFailed = 17;
    case PaymentFailedViaCCF = 18;

    case PaymentCompleted = 19;
    case PaymentCompletedViaCCF = 20;

    case PermanentlyRejectedBySamiti = 21;
    case PermanentlyRejectedByIC = 22;
    case PermanentlyRejectedByCCF = 23;
    case PermanentlyRejectedByDistrictUnion = 24;
    case PermanentlyRejectedByHQ = 25;
    case PermanentlyRejectedByAccounts = 26;

    case AccountDetailsUpdatedByHQ = 27;

    case FinalApplicationForPayment = 28;

    /**
     * Payment Processing
     *
     * CSV file generated and submitted to Axis Bank.
     * Awaiting payment response from the bank.
     */
    case PaymentBatchSubmitted = 99;
}
