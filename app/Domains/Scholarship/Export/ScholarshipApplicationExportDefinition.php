<?php

declare(strict_types=1);

namespace App\Domains\Scholarship\Export;

use App\Contracts\Services\ExportDefinitionInterface;
use App\Models\ScholarshipApplication;

final class ScholarshipApplicationExportDefinition implements ExportDefinitionInterface
{
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
            'application_number' => 'Application Number',
            'status_label' => 'Status',
            'student_name' => 'Student Name',
            'student_aadhaar' => 'Student Aadhaar',
            'mobile' => 'Mobile',
            'scheme' => 'Scheme',
            'academic_session' => 'Academic Session',
            'district_union' => 'District Union',
            'samiti' => 'Samiti',
            'phad' => 'Phad',
            'amount' => 'Amount',
            'is_draft' => 'Draft?',
            'last_action_role' => 'Last Action Role',
            'last_action_at' => 'Last Action At',
            'submitted_at' => 'Submitted At',
            'created_at' => 'Created At',
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
        return [
            'application_number' => $application->application_number ?? 'Draft #'.$application->id,
            'status_label' => $application->status_label,
            'student_name' => $application->student_name,
            'student_aadhaar' => $application->student_aadhaar,
            'mobile' => $application->mobile,
            'scheme' => $application->scheme?->name,
            'academic_session' => $application->academicSession?->name,
            'district_union' => $application->districtUnion?->name,
            'samiti' => $application->samiti?->name,
            'phad' => $application->phad?->name,
            'amount' => $application->amount,
            'is_draft' => $application->is_draft ? 'Yes' : 'No',
            'last_action_role' => $application->latestWorkflowTransition?->acted_by_role,
            'last_action_at' => $application->latestWorkflowTransition?->acted_at?->format('Y-m-d H:i:s'),
            'submitted_at' => $application->submitted_at?->format('Y-m-d H:i:s'),
            'created_at' => $application->created_at?->format('Y-m-d H:i:s'),
        ];
    }
}
