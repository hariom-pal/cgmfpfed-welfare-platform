<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class MasterSeeder extends Seeder
{
    /**
     * @var array<int, array{class: string, table: string, label: string, slug: string, seed: array<int, array{0: string, 1: string}>}>
     */
    private array $masters = [
        0 => [
            'class' => 'Scheme',
            'table' => 'schemes',
            'label' => 'Scheme',
            'slug' => 'schemes',
            'seed' => [
                0 => [
                    0 => 'SCH-MERIT',
                    1 => 'Award Scheme for Meritorious Students',
                ],
                1 => [
                    0 => 'SCH-EDU-INC',
                    1 => 'Education Proficiency Incentive Scheme',
                ],
                2 => [
                    0 => 'SCH-PROF',
                    1 => 'Scholarship Scheme for Professional Courses',
                ],
                3 => [
                    0 => 'SCH-NON-PROF',
                    1 => 'Scholarship Scheme for Non-Professional Courses',
                ],
            ],
        ],
        1 => [
            'class' => 'Course',
            'table' => 'courses',
            'label' => 'Course',
            'slug' => 'courses',
            'seed' => [
                0 => [
                    0 => 'CRS-10',
                    1 => 'Class 10',
                ],
                1 => [
                    0 => 'CRS-12',
                    1 => 'Class 12',
                ],
                2 => [
                    0 => 'CRS-BTECH',
                    1 => 'B.Tech',
                ],
                3 => [
                    0 => 'CRS-BA',
                    1 => 'Bachelor of Arts',
                ],
            ],
        ],
        2 => [
            'class' => 'Category',
            'table' => 'categories',
            'label' => 'Category',
            'slug' => 'categories',
            'seed' => [
                0 => [
                    0 => 'CAT-STUDENT',
                    1 => 'Student',
                ],
                1 => [
                    0 => 'CAT-NORMAL',
                    1 => 'Normal',
                ],
                2 => [
                    0 => 'CAT-PROF',
                    1 => 'Professional',
                ],
                3 => [
                    0 => 'CAT-NON-PROF',
                    1 => 'Non-Professional',
                ],
            ],
        ],
        3 => [
            'class' => 'Caste',
            'table' => 'castes',
            'label' => 'Caste',
            'slug' => 'castes',
            'seed' => [
                0 => [
                    0 => 'CST-ST',
                    1 => 'Scheduled Tribe',
                ],
                1 => [
                    0 => 'CST-SC',
                    1 => 'Scheduled Caste',
                ],
                2 => [
                    0 => 'CST-OBC',
                    1 => 'Other Backward Class',
                ],
                3 => [
                    0 => 'CST-GEN',
                    1 => 'General',
                ],
            ],
        ],
        4 => [
            'class' => 'Religion',
            'table' => 'religions',
            'label' => 'Religion',
            'slug' => 'religions',
            'seed' => [
                0 => [
                    0 => 'REL-HINDU',
                    1 => 'Hindu',
                ],
                1 => [
                    0 => 'REL-MUSLIM',
                    1 => 'Muslim',
                ],
                2 => [
                    0 => 'REL-CHRISTIAN',
                    1 => 'Christian',
                ],
                3 => [
                    0 => 'REL-SIKH',
                    1 => 'Sikh',
                ],
            ],
        ],
        5 => [
            'class' => 'District',
            'table' => 'districts',
            'label' => 'District',
            'slug' => 'districts',
            'seed' => [
                0 => [
                    0 => 'DST-646',
                    1 => 'Balod',
                ],
                1 => [
                    0 => 'DST-649',
                    1 => 'Balrampur',
                ],
                2 => [
                    0 => 'DST-636',
                    1 => 'Bijapur',
                ],
                3 => [
                    0 => 'DST-384',
                    1 => 'Korea',
                ],
                4 => [
                    0 => 'DST-648',
                    1 => 'Surajpur',
                ],
            ],
        ],
        6 => [
            'class' => 'DistrictUnion',
            'table' => 'district_unions',
            'label' => 'District Union',
            'slug' => 'district-unions',
            'seed' => [
                0 => [
                    0 => 'DUN-BALOD',
                    1 => 'Balod',
                ],
                1 => [
                    0 => 'DUN-KOREA',
                    1 => 'Korea',
                ],
                2 => [
                    0 => 'DUN-SURAJPUR',
                    1 => 'Surajpur',
                ],
                3 => [
                    0 => 'DUN-KAWARDHA',
                    1 => 'Kawardha',
                ],
            ],
        ],
        7 => [
            'class' => 'Samiti',
            'table' => 'samitis',
            'label' => 'Samiti',
            'slug' => 'samitis',
            'seed' => [
                0 => [
                    0 => 'SMT-BADB',
                    1 => 'Badbhum',
                ],
                1 => [
                    0 => 'SMT-BALOD',
                    1 => 'Balod',
                ],
                2 => [
                    0 => 'SMT-CHERPAL',
                    1 => 'Cherpal',
                ],
                3 => [
                    0 => 'SMT-BIHARPUR',
                    1 => 'Biharpur',
                ],
            ],
        ],
        8 => [
            'class' => 'Phad',
            'table' => 'phads',
            'label' => 'Phad',
            'slug' => 'phads',
            'seed' => [
                0 => [
                    0 => 'PHD-ARAJ',
                    1 => 'Arajgudra',
                ],
                1 => [
                    0 => 'PHD-BADB',
                    1 => 'Badbhum',
                ],
                2 => [
                    0 => 'PHD-CHERPAL',
                    1 => 'Cherpal',
                ],
                3 => [
                    0 => 'PHD-KANJIYA',
                    1 => 'Kanjiya',
                ],
            ],
        ],
        9 => [
            'class' => 'DocumentType',
            'table' => 'document_types',
            'label' => 'Document Type',
            'slug' => 'document-types',
            'seed' => [
                0 => [
                    0 => 'DOC-AADHAAR',
                    1 => 'Aadhaar',
                ],
                1 => [
                    0 => 'DOC-PHOTO',
                    1 => 'Photo',
                ],
                2 => [
                    0 => 'DOC-10TH',
                    1 => '10th Marksheet',
                ],
                3 => [
                    0 => 'DOC-12TH',
                    1 => '12th Marksheet',
                ],
                4 => [
                    0 => 'DOC-INCOME',
                    1 => 'Income Certificate',
                ],
                5 => [
                    0 => 'DOC-CASTE',
                    1 => 'Caste Certificate',
                ],
                6 => [
                    0 => 'DOC-RESIDENCE',
                    1 => 'Residence Certificate',
                ],
                7 => [
                    0 => 'DOC-BANK',
                    1 => 'Bank Passbook',
                ],
            ],
        ],
        10 => [
            'class' => 'WorkflowStatus',
            'table' => 'workflow_statuses',
            'label' => 'Workflow Status',
            'slug' => 'workflow-statuses',
            'seed' => [
                0 => [
                    0 => 'WF-PENDING',
                    1 => 'Pending',
                ],
                1 => [
                    0 => 'WF-SAMITI-APPROVED',
                    1 => 'Samiti Approved',
                ],
                2 => [
                    0 => 'WF-SAMITI-REJECTED',
                    1 => 'Samiti Rejected',
                ],
                3 => [
                    0 => 'WF-IC-APPROVED',
                    1 => 'IC Approved',
                ],
                4 => [
                    0 => 'WF-IC-REJECTED',
                    1 => 'IC Rejected',
                ],
                5 => [
                    0 => 'WF-DU-APPROVED',
                    1 => 'District Union Approved',
                ],
                6 => [
                    0 => 'WF-DU-REJECTED',
                    1 => 'District Union Rejected',
                ],
                7 => [
                    0 => 'WF-HQ-APPROVED',
                    1 => 'HQ Approved',
                ],
                8 => [
                    0 => 'WF-HQ-REJECTED',
                    1 => 'HQ Rejected',
                ],
                9 => [
                    0 => 'WF-FINANCE-PENDING',
                    1 => 'Finance Pending',
                ],
                10 => [
                    0 => 'WF-PAYMENT-COMPLETED',
                    1 => 'Payment Completed',
                ],
            ],
        ],
        11 => [
            'class' => 'RejectionReason',
            'table' => 'rejection_reasons',
            'label' => 'Rejection Reason',
            'slug' => 'rejection-reasons',
            'seed' => [
                0 => [
                    0 => 'RR-DOC-MISSING',
                    1 => 'Required document missing',
                ],
                1 => [
                    0 => 'RR-DOC-INVALID',
                    1 => 'Uploaded document is invalid',
                ],
                2 => [
                    0 => 'RR-DUPLICATE',
                    1 => 'Duplicate application',
                ],
                3 => [
                    0 => 'RR-ELIGIBILITY',
                    1 => 'Eligibility criteria not met',
                ],
                4 => [
                    0 => 'RR-BANK',
                    1 => 'Bank account details invalid',
                ],
            ],
        ],
        12 => [
            'class' => 'NotificationTemplate',
            'table' => 'notification_templates',
            'label' => 'Notification Template',
            'slug' => 'notification-templates',
            'seed' => [
                0 => [
                    0 => 'NT-APP-SUBMITTED',
                    1 => 'Application Submitted',
                ],
                1 => [
                    0 => 'NT-APP-APPROVED',
                    1 => 'Application Approved',
                ],
                2 => [
                    0 => 'NT-APP-REJECTED',
                    1 => 'Application Rejected',
                ],
                3 => [
                    0 => 'NT-PAYMENT-DONE',
                    1 => 'Payment Completed',
                ],
            ],
        ],
    ];

    public function run(): void
    {
        foreach ($this->masters as $master) {
            foreach ($master['seed'] as $row) {
                DB::table($master['table'])->updateOrInsert(
                    ['code' => $row[0]],
                    [
                        'uuid' => (string) Str::uuid(),
                        'name' => $row[1],
                        'description' => $master['label'].' master record',
                        'is_active' => true,
                        'created_by' => null,
                        'updated_by' => null,
                        'deleted_by' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                );
            }
        }

        $schemeIds = DB::table('schemes')->pluck('id');
        $documentTypeIds = DB::table('document_types')->pluck('id');

        foreach ($schemeIds as $schemeId) {
            foreach ($documentTypeIds->take(4) as $documentTypeId) {
                DB::table('scheme_documents')->updateOrInsert(
                    ['scheme_id' => $schemeId, 'document_type_id' => $documentTypeId],
                    ['created_at' => now(), 'updated_at' => now()],
                );
            }
        }

        $rejectedStatuses = DB::table('workflow_statuses')->where('name', 'like', '%Rejected')->pluck('id');
        $reasonIds = DB::table('rejection_reasons')->pluck('id');

        foreach ($rejectedStatuses as $statusId) {
            foreach ($reasonIds as $reasonId) {
                DB::table('workflow_rejection_reasons')->updateOrInsert(
                    ['workflow_status_id' => $statusId, 'rejection_reason_id' => $reasonId],
                    ['created_at' => now(), 'updated_at' => now()],
                );
            }
        }
    }
}
