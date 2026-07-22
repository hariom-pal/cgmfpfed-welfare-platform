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
     * Source-system CCF workflow states retained for migrated applications.
     * New applications must never enter these states.
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

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Application Received Under Samiti Verification',
            self::Resubmitted => 'Application Resubmitted',
            self::RejectedBySamiti => 'Returned by Samiti',
            self::RejectedByIC => 'Returned by IC',
            self::RecommendedBySamiti => 'Recommended by Samiti',
            self::RecommendedByIC => 'Recommended by IC',
            self::RejectedByCCF => 'Returned by CCF',
            self::RecommendedByCCF => 'Recommended by CCF',
            self::RejectedByDistrictUnion => 'Returned by District Union',
            self::RejectedByDistrictUnionViaCCF => 'Returned by District Union via CCF',
            self::RecommendedByDistrictUnion => 'Recommended by District Union',
            self::RecommendedByDistrictUnionViaCCF => 'Recommended by District Union via CCF',
            self::RejectedByHQ => 'Returned by HQ',
            self::RejectedByHQViaCCF => 'Returned by HQ via CCF',
            self::RecommendedForPayment => 'Recommended for Payment',
            self::RecommendedForPaymentViaCCF => 'Recommended for Payment via CCF',
            self::PaymentFailed => 'Payment Failed',
            self::PaymentFailedViaCCF => 'Payment Failed via CCF',
            self::PaymentCompleted => 'Payment Completed',
            self::PaymentCompletedViaCCF => 'Payment Completed via CCF',
            self::PermanentlyRejectedBySamiti => 'Permanently Rejected by Samiti',
            self::PermanentlyRejectedByIC => 'Permanently Rejected by IC',
            self::PermanentlyRejectedByCCF => 'Permanently Rejected by CCF',
            self::PermanentlyRejectedByDistrictUnion => 'Permanently Rejected by District Union',
            self::PermanentlyRejectedByHQ => 'Permanently Rejected by HQ',
            self::PermanentlyRejectedByAccounts => 'Returned by Finance',
            self::AccountDetailsUpdatedByHQ => 'Account Details Updated',
            self::FinalApplicationForPayment => 'Final Application for Payment',
            self::PaymentBatchSubmitted => 'Payment Batch Submitted',
            self::AppealedByBeneficiary => 'Appealed by Beneficiary',
        };
    }

    public function stage(): string
    {
        return match ($this) {
            self::Pending, self::Resubmitted => 'samiti',
            self::RecommendedBySamiti, self::RejectedByIC => 'ic',
            self::RecommendedByIC, self::RejectedByDistrictUnion => 'district_union',
            self::RecommendedByDistrictUnion, self::RejectedByHQ => 'hq',
            self::RecommendedForPayment, self::PermanentlyRejectedByAccounts, self::AccountDetailsUpdatedByHQ, self::FinalApplicationForPayment, self::PaymentBatchSubmitted, self::PaymentFailed => 'finance',
            self::PaymentCompleted => 'completed',
            self::PermanentlyRejectedBySamiti, self::PermanentlyRejectedByIC, self::PermanentlyRejectedByDistrictUnion, self::PermanentlyRejectedByHQ => 'closed',
            default => 'source_system',
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [
            self::PaymentCompleted,
            self::PermanentlyRejectedBySamiti,
            self::PermanentlyRejectedByIC,
            self::PermanentlyRejectedByDistrictUnion,
            self::PermanentlyRejectedByHQ,
        ], true);
    }

    public function isEditableByVleAfterReturn(): bool
    {
        return in_array($this, [
            self::RejectedBySamiti,
            self::RejectedByIC,
            self::RejectedByCCF,
            self::RejectedByDistrictUnion,
            self::RejectedByDistrictUnionViaCCF,
            self::RejectedByHQ,
            self::RejectedByHQViaCCF,
            self::PermanentlyRejectedByAccounts,
        ], true);
    }

    /**
     * @return list<int>
     */
    public static function pendingAtVleValues(): array
    {
        return [
            self::Pending->value,
            self::Resubmitted->value,
        ];
    }

    /**
     * @return list<int>
     */
    public static function underProcessValues(): array
    {
        return collect(self::cases())
            ->reject(fn (self $status): bool => in_array($status, [
                self::Pending,
                self::Resubmitted,
                ...self::completedCases(),
                ...self::failedCases(),
                ...self::rejectedCases(),
            ], true))
            ->map(fn (self $status): int => $status->value)
            ->values()
            ->all();
    }

    /**
     * @return list<int>
     */
    public static function completedValues(): array
    {
        return array_map(fn (self $status): int => $status->value, self::completedCases());
    }

    /**
     * @return list<int>
     */
    public static function failedValues(): array
    {
        return array_map(fn (self $status): int => $status->value, self::failedCases());
    }

    /**
     * @return list<int>
     */
    public static function rejectedValues(): array
    {
        return array_map(fn (self $status): int => $status->value, self::rejectedCases());
    }

    /**
     * @return list<self>
     */
    private static function completedCases(): array
    {
        return [self::PaymentCompleted, self::PaymentCompletedViaCCF];
    }

    /**
     * @return list<self>
     */
    private static function failedCases(): array
    {
        return [self::PaymentFailed, self::PaymentFailedViaCCF];
    }

    /**
     * @return list<self>
     */
    private static function rejectedCases(): array
    {
        return [
            self::RejectedBySamiti,
            self::RejectedByIC,
            self::RejectedByCCF,
            self::RejectedByDistrictUnion,
            self::RejectedByDistrictUnionViaCCF,
            self::RejectedByHQ,
            self::RejectedByHQViaCCF,
            self::PermanentlyRejectedBySamiti,
            self::PermanentlyRejectedByIC,
            self::PermanentlyRejectedByCCF,
            self::PermanentlyRejectedByDistrictUnion,
            self::PermanentlyRejectedByHQ,
            self::PermanentlyRejectedByAccounts,
        ];
    }
}
