<?php

declare(strict_types=1);

namespace App\Domains\Scholarship\Export;

use App\Contracts\Services\ExportDefinitionInterface;
use App\Models\ScholarshipApplication;
use App\Models\ScholarshipWorkflowTransition;
use App\Services\RoleService;
use Illuminate\Support\Str;

final class ScholarshipApplicationExportDefinition implements ExportDefinitionInterface
{
    public function __construct(private readonly RoleService $roles) {}

    public function module(): string
    {
        return 'scholarship_applications';
    }

    public function label(): string
    {
        return 'Scholarship Applications';
    }

    public function availableFields(): array
    {
        return [
            // Application Details
            'application_number' => 'Application Number',
            'student_name' => 'Student Name',
            'student_aadhaar' => 'Student Aadhaar',
            'mobile' => 'Mobile',
            'scheme' => 'Scheme',
            'academic_session' => 'Academic Session',
            'status_label' => 'Application Status',
            'payment_status' => 'Payment Status',
            'is_draft' => 'Draft?',

            // VLE Details
            'legacy_added_by' => 'Added By (CSC ID)',

            // Workflow Details
            'current_role' => 'Current Stage',
            'last_action_role' => 'Last Action Role',
            'last_action_by' => 'Last Action By',
            'last_action_date' => 'Last Action Date',
            'last_action_remarks' => 'Last Action Remarks',

            // Organization Details
            'district_union' => 'District Union',
            'samiti' => 'Samiti',
            'phad' => 'Phad',

            // Payment Details
            'wallet_transaction_status' => 'Wallet Transaction Status',
            'wallet_transaction_id' => 'Wallet Transaction ID',
            'payment_date' => 'Payment Date',
            'payment_amount' => 'Payment Amount',

            // System Details
            'submitted_at' => 'Submitted At',
            'created_at' => 'Created At',
            'updated_at' => 'Updated At',
        ];
    }

    public function resolveRow(mixed $row): array
    {
        if (! $row instanceof ScholarshipApplication) {
            return [];
        }

        return $this->resolveApplicationRow($row);
    }

    /**
     * @return array<string, string|int|float|null>
     */
    private function resolveApplicationRow(ScholarshipApplication $application): array
    {
        $lastAction = $application->latestWorkflowTransition ?? $application->latestAudit;
        $lastActionRole = $lastAction instanceof ScholarshipWorkflowTransition
            ? ($lastAction->acted_by_role ?: null)
            : ($lastAction?->actor ? $this->roles->name($lastAction->actor) : null);
        $wallet = $application->latestWalletTransaction;

        return [
            'application_number' => $application->application_number ?? 'Draft #'.$application->id,
            'student_name' => $application->student_name,
            'student_aadhaar' => $application->student_aadhaar,
            'mobile' => $application->mobile,
            'scheme' => $application->scheme?->name,
            'academic_session' => $application->academicSession?->name,
            'status_label' => $application->status_label,
            'payment_status' => $application->payment_status,
            'is_draft' => $application->is_draft ? 'Yes' : 'No',

            'legacy_added_by' => $application->legacy_added_by ?? $application->applicant?->csc_id,

            'current_role' => Str::of(str_replace('_', ' ', $application->workflow_stage?->value ?? ''))->title()->toString(),
            'last_action_role' => $lastActionRole,
            'last_action_by' => $lastAction?->actor?->name,
            'last_action_date' => $lastAction?->acted_at?->format('Y-m-d H:i:s'),
            'last_action_remarks' => $lastAction?->remarks,

            'district_union' => $application->districtUnion?->name,
            'samiti' => $application->samiti?->name,
            'phad' => $application->phad?->name,

            'wallet_transaction_status' => $wallet?->status,
            'wallet_transaction_id' => $wallet?->reference,
            'payment_date' => $application->paid_at?->format('Y-m-d H:i:s'),
            'payment_amount' => $application->amount,

            'submitted_at' => $application->submitted_at?->format('Y-m-d H:i:s'),
            'created_at' => $application->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $application->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
