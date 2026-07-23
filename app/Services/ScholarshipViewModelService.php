<?php

declare(strict_types=1);

namespace App\Services;

use App\Domains\Scholarship\Enums\ScholarshipApplicationStatus;
use App\Models\Scheme;
use App\Models\ScholarshipApplication;
use App\Models\ScholarshipApplicationAudit;
use App\Models\ScholarshipWorkflowTransition;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

final class ScholarshipViewModelService
{
    public function __construct(private readonly RoleService $roles) {}

    /**
     * @return array<string, mixed>
     */
    public function schemeSelection(string $mode, array $query = []): array
    {
        $isCreate = $mode === 'create';

        return [
            'mode' => $mode,
            'title' => $isCreate ? 'Add Application' : 'Scholarship Applications',
            'heading' => $isCreate ? 'Add Scholarship Application' : 'Scholarship Applications',
            'subtitle' => 'Select a scheme to continue',
            'cardTitle' => $isCreate
                ? 'Select a Scheme to continue to add application'
                : 'Select a Scheme to view applications',
            'breadcrumbs' => ['Applications' => route('applications.index'), $isCreate ? 'Add' : 'Scheme' => null],
            'schemes' => Scheme::query()
                ->where('is_active', true)
                ->orderBy('id')
                ->get()
                ->map(fn (Scheme $scheme): array => [
                    'id' => $scheme->id,
                    'name' => $scheme->name,
                    'url' => $isCreate
                        ? route('applications.create.scheme', $scheme)
                        : route('applications.index', array_merge($query, ['scheme' => $scheme->id])),
                ]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function applicationDetails(ScholarshipApplication $application): array
    {
        $legacyApplication = $this->legacyApplicationPayload($application);
        $masters = $this->masterNames($application, $legacyApplication);
        $verification = $this->latestLegacyVerification($application);
        $documents = $application->currentDocuments->keyBy('document_type');
        $documentLabels = $this->productionDocumentLabels($application);

        return [
            'application' => $application,
            'breadcrumbs' => ['Applications' => route('applications.index'), 'Details' => null],
            'sections' => $this->detailSections($application, $masters, $legacyApplication),
            'documentLabels' => $documentLabels,
            'documentRows' => $this->documentRows($application, $documentLabels, $documents),
            'previewDocuments' => $this->previewDocuments($application, $documentLabels),
            'collectionRows' => $this->collectionRows($application, $verification),
            'collectionSummary' => $this->collectionSummary($application, $verification),
            'statusSummary' => $this->statusSummary($application),
            'statusLabels' => $this->statusLabels(),
            'submittedBy' => $this->submittedBy($application),
            'auditTrail' => $this->auditTrail($application),
        ];
    }

    /**
     * Submission identity: the application must always be traceable back to the VLE who
     * submitted it. `applicant_user_id` is only ever set going forward and is never
     * backfilled for legacy-imported applications, so this always falls back to the legacy
     * CSC ID (`legacy_added_by`) recorded on every migrated application, and separately
     * resolves a Laravel user by matching `csc_id` if that VLE has since logged in — without
     * ever reading or writing `applicant_user_id` for that resolution.
     *
     * @return array{name: ?string, cscId: ?string, linkedUser: ?User}
     */
    private function submittedBy(ScholarshipApplication $application): array
    {
        $linkedUser = $application->resolveLegacyVleUser();

        return [
            'name' => $linkedUser?->name,
            'cscId' => $application->legacy_added_by ?? $linkedUser?->csc_id,
            'linkedUser' => $linkedUser,
        ];
    }

    /**
     * Unified, chronological audit trail. Every native workflow action writes both a
     * `scholarship_workflow_transitions` row (typed, has `acted_by_role` already) and a
     * `scholarship_application_audits` row (legacy-shaped) for the same event, so audits are
     * only included when they predate the application's first transition — i.e. genuine
     * legacy history — to avoid showing the same action twice.
     *
     * @return list<array{actedAt: mixed, action: string, actorName: ?string, role: ?string, districtUnion: ?string, samiti: ?string, remarks: ?string}>
     */
    private function auditTrail(ScholarshipApplication $application): array
    {
        $transitions = $application->workflowTransitions->map(fn (ScholarshipWorkflowTransition $transition): array => [
            'actedAt' => $transition->acted_at,
            'action' => Str::of(str_replace('_', ' ', $transition->action))->title()->toString(),
            'actorName' => $transition->actor?->name,
            'role' => $transition->acted_by_role ?: ($transition->actor ? $this->roles->name($transition->actor) : null),
            'districtUnion' => $transition->actor?->districtUnionMaster?->name,
            'samiti' => $transition->actor?->samitiMaster?->name,
            'remarks' => $transition->remarks,
        ]);

        $earliestTransitionAt = $application->workflowTransitions->min('acted_at');

        $audits = $application->audits
            ->when($earliestTransitionAt !== null, fn ($audits) => $audits->filter(
                fn (ScholarshipApplicationAudit $audit): bool => $audit->acted_at !== null && $audit->acted_at->lt($earliestTransitionAt)
            ))
            ->map(fn (ScholarshipApplicationAudit $audit): array => [
                'actedAt' => $audit->acted_at,
                'action' => ScholarshipApplicationStatus::tryFrom((int) $audit->to_status)?->label()
                    ?? Str::of(str_replace('_', ' ', $audit->action))->title()->toString(),
                'actorName' => $audit->actor?->name,
                'role' => $audit->actor ? $this->roles->name($audit->actor) : null,
                'districtUnion' => $audit->actor?->districtUnionMaster?->name,
                'samiti' => $audit->actor?->samitiMaster?->name,
                'remarks' => $audit->remarks,
            ]);

        return $transitions->concat($audits)
            ->sortByDesc('actedAt')
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{title: string, icon: string, fields: array<int, array{label: string, value: mixed, class?: string}>}>
     */
    private function detailSections(ScholarshipApplication $application, array $masters, array $legacyApplication): array
    {
        $schemeId = (int) $application->scheme_id;
        $isProfessional = in_array($schemeId, [3, 4], true);
        $isAdvancedYear = $isProfessional && (int) $application->current_year_of_study > 1;
        $metadata = $application->metadata ?? [];

        $sections = [
            [
                'title' => 'Information Regarding Primary Society / प्राथमिक सोसायटी के संबंध में जानकारी',
                'icon' => 'fa-regular fa-file-lines',
                'fields' => array_values(array_filter([
                    $this->field('Scheme / योजना', $application->scheme?->name),
                    $isProfessional ? $this->field('Select Education Year / शिक्षा वर्ष चुनें', $application->current_year_of_study) : null,
                    $this->field('District Union / जिला संघ', $masters['district_union']),
                    $this->field('Primary Society / समिति का नाम', $masters['samiti']),
                    $this->field('PHAD Name / फड़ का नाम', $masters['phad']),
                    $this->field('District / ज़िला', $masters['district']),
                    $this->field('Block / ब्लॉक', $masters['block']),
                    $application->area === 'Rural' ? $this->field('Gram Panchayat / ग्राम पंचायत', $masters['gram_panchayat']) : $this->field('City / शहर', $masters['city']),
                    $application->area === 'Rural' ? $this->field('Village / गाँव', $masters['village']) : $this->field('Ward / वार्ड', $masters['ward']),
                    $application->area === 'Rural' ? null : $this->field('Ward Number / वार्ड संख्या', $masters['ward_number']),
                ])),
            ],
            [
                'title' => 'Head of Family Detail / परिवार मुखिया का विवरण',
                'icon' => 'fa-solid fa-people-roof',
                'fields' => [
                    $this->field('Sangrahak Card Number / संग्राहक कार्ड नंबर', $application->sangrahak_card_number),
                    $this->field('Head of sangrahak family name / परिवार के मुखिया का नाम', $application->head_of_family_name),
                    $this->field('Aadhaar Number Of Head / मुखिया का आधार नंबर', $application->head_of_family_aadhaar),
                ],
            ],
        ];

        if (in_array($schemeId, [1, 2], true)) {
            $sections[] = [
                'title' => 'Head of Family Bank Detail / परिवार के प्रमुख बैंक का विवरण',
                'icon' => 'fa-solid fa-building-columns',
                'fields' => [
                    $this->field('Account Number / खाता संख्या', $this->value($legacyApplication, 'haccountnumber', data_get($metadata, 'legacy_head_of_family_bank.account_number'))),
                    $this->field('Account Holder Name / खाता धारक का नाम', $this->value($legacyApplication, 'haccountname', data_get($metadata, 'legacy_head_of_family_bank.account_holder'))),
                    $this->field('IFSC Code / आईएफएससी कोड', $this->value($legacyApplication, 'hifsc', data_get($metadata, 'legacy_head_of_family_bank.ifsc'))),
                    $this->field('Bank Name / बैंक का नाम', $this->value($legacyApplication, 'hbankname', data_get($metadata, 'legacy_head_of_family_bank.bank_name'))),
                    $this->field('Branch Name of Bank / बैंक की शाखा का नाम', $this->value($legacyApplication, 'hbranch', data_get($metadata, 'legacy_head_of_family_bank.branch'))),
                ],
            ];
        }

        $sections[] = [
            'title' => 'Information of Student / छात्र की जानकारी',
            'icon' => 'fa-solid fa-user-graduate',
            'fields' => [
                $this->field('Name of Student / छात्र का नाम', $application->student_name),
                $this->field('Gender / लिंग', $application->gender),
                $this->field('Student Date of Birth / छात्र की जन्मतिथि', $application->date_of_birth?->format('Y-m-d')),
                $this->field('Aadhaar Number Of Student / छात्र का आधार नंबर', $application->student_aadhaar),
                $this->field('Address / पता', $application->address, 'col-12'),
                $this->field('Pin Code / पिन कोड', $application->pincode),
                $this->field('Contact Number / संपर्क नंबर', $application->mobile),
            ],
        ];

        $sections[] = [
            'title' => $isAdvancedYear ? 'Detail of Professional Course / प्रोफेशनल कोर्स का विवरण' : 'Student Educational Detail / छात्र शैक्षिक विवरण',
            'icon' => 'fa-solid fa-school',
            'fields' => $this->educationFields($application, $isProfessional, $isAdvancedYear),
        ];

        if ($isProfessional) {
            $sections[] = [
                'title' => 'Student Bank Details / छात्र बैंक विवरण',
                'icon' => 'fa-solid fa-building-columns',
                'fields' => [
                    $this->field('Account Number / खाता संख्या', $application->student_bank_account_number),
                    $this->field('Account Holder Name / खाता धारक का नाम', $application->student_bank_account_holder_name),
                    $this->field('IFSC Code / आईएफएससी कोड', $application->student_bank_ifsc),
                    $this->field('Bank Name / बैंक का नाम', $application->student_bank_name),
                    $this->field('Branch Name of Bank / बैंक की शाखा का नाम', $application->student_bank_branch),
                ],
            ];
        }

        return $sections;
    }

    /**
     * @return array<int, array{label: string, value: mixed, class?: string}>
     */
    private function educationFields(ScholarshipApplication $application, bool $isProfessional, bool $isAdvancedYear): array
    {
        $courseLabel = ((int) $application->scheme_id === 4 ? 'Non Professional Course Name' : 'Professional Course Name').' / प्रोफेशनल कोर्स का नाम';

        $fields = $isAdvancedYear
            ? [
                $this->field($courseLabel, $application->course_name),
                $this->field('Session of 1st year in Professional course(Year)', $application->first_year_session),
                $this->field('University Name / विश्वविद्यालय का नाम', $application->board_university),
                $this->field('Institute Name / संस्थान का नाम', $application->institution_name),
                $this->field('Session for which student is applying', $application->scholarshipSession?->name ?? $application->scholarship_session),
            ]
            : [
                $this->field('School Name / स्कूल के नाम'.($isProfessional ? ' of Class 12th' : ''), $application->school_college_name),
                $this->field('Passing Year / उत्तीर्ण वर्ष'.($isProfessional ? ' of Class 12th' : ''), $application->admission_year),
                $this->field('Passing Class / उत्तीर्ण कक्षा'.($isProfessional ? ' of Class 12th' : ''), $application->class),
            ];

        if ($isProfessional && ! $isAdvancedYear) {
            $fields = array_merge($fields, [
                $this->field($courseLabel, $application->course_name),
                $this->field('Course Duration (in Years) / कोर्स अवधि', $application->course_duration),
                $this->field('Institute Name / संस्थान का नाम', $application->institution_name),
                $this->field('University Name / विश्वविद्यालय का नाम', $application->board_university),
            ]);
        }

        return array_merge($fields, [
            $this->field('Marks Obtained / प्राप्त अंक', $application->marks_obtained),
            $this->field('Total Marks / कुल अंक', $application->maximum_marks),
            $this->field('Marks in Percentage / प्रतिशत में अंक', $application->percentage),
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function documentRows(ScholarshipApplication $application, array $documentLabels, mixed $documents): array
    {
        $rows = [];
        foreach ($documentLabels as $type => $label) {
            $document = $documents->get($type);
            $rows[] = [
                'type' => $type,
                'label' => $label,
                'linkLabel' => 'View '.trim(strtok($label, '/')),
                'document' => $document,
                'showUrl' => $document ? route('applications.documents.show', [$application, $document]) : null,
                'downloadUrl' => $document ? route('applications.documents.download', [$application, $document]) : null,
            ];
        }

        return $rows;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function previewDocuments(ScholarshipApplication $application, array $documentLabels): array
    {
        return $application->currentDocuments
            ->filter(fn ($document): bool => $document->isImage() || $document->isPdf())
            ->map(fn ($document): array => [
                'document' => $document,
                'label' => $documentLabels[$document->document_type] ?? str_replace('_', ' ', $document->document_type),
                'showUrl' => route('applications.documents.show', [$application, $document]),
                'isImage' => $document->isImage(),
                'isPdf' => $document->isPdf(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{year: mixed, quantity: mixed, tpCard: mixed, verified: string}>
     */
    private function collectionRows(ScholarshipApplication $application, array $verification): array
    {
        return $application->tendupattaCollections
            ->values()
            ->map(fn ($collection, int $index): array => [
                'year' => $collection->collection_year,
                'quantity' => $collection->quantity_gaddi,
                'tpCard' => $verification['tp_card'.($index + 1)] ?? 'N/A',
                'verified' => $collection->is_verified ? 'Yes' : 'No',
            ])
            ->all();
    }

    /**
     * @return array<int, array{label: string, value: mixed, class?: string}>
     */
    private function collectionSummary(ScholarshipApplication $application, array $verification): array
    {
        $fields = [
            $this->field('Status', $this->statusLabels()[(int) $application->status] ?? $application->status_label),
            $this->field('Feedback', $verification['reason'] ?? $application->tendupattaCollections->first()?->remarks),
        ];

        if (filled($verification['phadbookfile'] ?? null)) {
            $fields[] = $this->field('Phad Book', (string) $verification['phadbookfile']);
        }

        return $fields;
    }

    /**
     * @return array<string, mixed>
     */
    private function statusSummary(ScholarshipApplication $application): array
    {
        return [
            'applicationNumber' => $application->application_number,
            'status' => $this->statusLabels()[(int) $application->status] ?? $application->status_label,
            'amount' => '₹'.number_format((float) $application->amount, 2),
            'payment' => $application->payment_status ?? 'N/A',
            'reference' => $application->payment_reference_id ?? 'N/A',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function legacyApplicationPayload(ScholarshipApplication $application): array
    {
        if (! $application->legacy_application_id || ! Schema::hasTable('source_data_archives')) {
            return [];
        }

        $row = DB::table('source_data_archives')
            ->where('source_table', 'application')
            ->where('source_primary_key', (string) $application->legacy_application_id)
            ->first();

        return $row ? (array) json_decode((string) $row->payload, true) : [];
    }

    /**
     * @return array<string, mixed>
     */
    private function masterNames(ScholarshipApplication $application, array $legacyApplication): array
    {
        $districtCode = $this->value($legacyApplication, 'district', $this->districtCodeFromApplication($application));
        $phadCode = $this->value($legacyApplication, 'phadname', $this->phadCodeFromApplication($application));
        $wardNumber = $this->value($legacyApplication, 'ward_number', $application->ward_number);

        return [
            'district' => $application->district?->name
                ?? $this->archiveValue('districts', 'district_code', $districtCode, 'district_name'),
            'district_union' => $application->districtUnion?->name
                ?? $this->archiveValue('district_union', 'id', $application->district_union_id, 'union_name'),
            'samiti' => $application->samiti?->name
                ?? $this->archiveValue('samiti', 'id', $application->samiti_id, 'samiti_name'),
            'phad' => $this->phadName($application, $phadCode),
            'block' => $application->block?->name
                ?? $this->archiveValue('blocks', 'block_code', $application->block_code, 'block_name'),
            'gram_panchayat' => $application->gramPanchayat?->name
                ?? $this->archiveValue('gram_panchayat', 'gp_code', $application->gram_panchayat_code, 'gp_name'),
            'village' => $application->village?->name
                ?? $this->archiveValue('villages', 'village_code', $application->village_code, 'village_name'),
            'city' => $application->city?->name
                ?? $this->archiveValue('cities', 'city_code', $application->city_code, 'city_name'),
            'ward' => $application->ward?->name
                ?? $this->archiveValue('wards', 'ward_code', $application->ward_code, 'ward_name'),
            'ward_number' => $wardNumber,
        ];
    }

    private function phadName(ScholarshipApplication $application, mixed $phadCode): ?string
    {
        if (filled($phadCode)) {
            $name = DB::table('phads')
                ->where('code', 'like', 'PHD-'.$phadCode.'-%')
                ->value('name');

            if (filled($name)) {
                return (string) $name;
            }

            $name = $this->archiveValue('phads', 'phad_code', $phadCode, 'phad_name');
            if (filled($name)) {
                return $name;
            }
        }

        return $application->phad?->name;
    }

    private function districtCodeFromApplication(ScholarshipApplication $application): ?string
    {
        if ($application->district?->code && str_starts_with($application->district->code, 'DST-')) {
            return substr($application->district->code, 4);
        }

        return $application->district_id !== null ? (string) $application->district_id : null;
    }

    private function phadCodeFromApplication(ScholarshipApplication $application): ?string
    {
        if ($application->phad?->code && preg_match('/^PHD-(.+)-\d+$/', $application->phad->code, $matches) === 1) {
            return $matches[1];
        }

        return $application->phad_id !== null ? (string) $application->phad_id : null;
    }

    private function archiveValue(string $table, string $keyColumn, mixed $keyValue, string $nameColumn): ?string
    {
        if (! filled($keyValue) || ! Schema::hasTable('source_data_archives')) {
            return null;
        }

        $row = DB::table('source_data_archives')
            ->where('source_table', $table)
            ->get()
            ->first(function (object $archive) use ($keyColumn, $keyValue): bool {
                $payload = (array) json_decode((string) $archive->payload, true);

                return (string) ($payload[$keyColumn] ?? '') === (string) $keyValue;
            });

        if (! $row) {
            return null;
        }

        $payload = (array) json_decode((string) $row->payload, true);
        $value = $payload[$nameColumn] ?? null;

        return filled($value) ? (string) $value : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function latestLegacyVerification(ScholarshipApplication $application): array
    {
        if (! $application->application_number || ! Schema::hasTable('source_data_archives')) {
            return [];
        }

        $row = DB::table('source_data_archives')
            ->where('source_table', 'application_verify')
            ->get()
            ->filter(function (object $archive) use ($application): bool {
                $payload = (array) json_decode((string) $archive->payload, true);

                return (string) ($payload['application_id'] ?? '') === (string) $application->application_number;
            })
            ->sortByDesc(fn (object $archive): int => (int) $archive->source_primary_key)
            ->first();

        return $row ? (array) json_decode((string) $row->payload, true) : [];
    }

    /**
     * @return array<string, string>
     */
    private function productionDocumentLabels(ScholarshipApplication $application): array
    {
        $labels = [
            'tpcard' => 'Sangrahak Card / संग्राहक कार्ड',
            'aadharcard' => 'Aadhaar Card of Student / छात्र का आधार कार्ड',
            'haadharcard' => 'Aadhaar Card of Head of Family / परिवार के मुखिया का आधार कार्ड',
            'admission_copy' => 'Marksheet Copy / मार्कशीट कॉपी',
        ];

        if (in_array((int) $application->scheme_id, [3, 4], true)) {
            $labels['passbook'] = 'Student Bank Passbook (Student PHOTO AND BANK DETAILS) / छात्र बैंक पासबुक';
            $labels['admission_receipt'] = 'Admission Receipt / प्रवेश रसीद';
        } else {
            // Legacy (detail_scholarship.php) renders exactly one passbook slot for schemes 1/2:
            // `head_passbook`, whose link text happens to read "View Front Page of Passbook" —
            // that is a link caption, not a second document. A separate `passbook` document_type
            // is never created for these schemes (Scholarship.php's scheme-1/2 upload branch never
            // writes it), so adding it here was a phantom slot that always rendered "Not uploaded".
            $labels['head_passbook'] = 'Head of Family Bank Passbook (HEAD OF FAMILY PHOTO AND BANK DETAILS) / परिवार बैंक पासबुक';
        }

        if ($application->currentDocuments->contains('document_type', 'phadbookfile')) {
            $labels['phadbookfile'] = 'Phad Book / फड़ बुक';
        }

        return $labels;
    }

    /**
     * @return array<int, string>
     */
    private function statusLabels(): array
    {
        return [
            'Pending', 'Resubmitted by VLE', 'Not Recommended by Samiti', 'Not Recommended by IC',
            'Recommended by Samiti', 'Recommended by IC', 'Appealed by Beneficiary', 'Rejected By CCF',
            'Recommended by CCF', 'Not Recommended By DU', 'Not Recommended By DU', 'Approved By DU',
            'Approved By DU', 'Rejected By HQ', 'Rejected By HQ', 'Recommended For Payment',
            'Recommended For Payment', 'Payment Failed', 'Payment Failed', 'Payment Completed',
            'Payment Completed', 'Permanent Rejected By Samiti', 'Permanent Rejected By IC',
            'Permanent Rejected By CCF', 'Permanent Rejected By DU', 'Permanent Rejected By HQ',
            'Permanent Rejected By Accounts', 'Payment Batch Submitted', 'Final Application for Payment',
        ];
    }

    /**
     * @return array{label: string, value: mixed, class?: string}
     */
    private function field(string $label, mixed $value, string $class = 'col-md-6'): array
    {
        return ['label' => $label, 'value' => $value, 'class' => $class];
    }

    private function value(array $legacy, string $key, mixed $fallback = null): mixed
    {
        return filled($legacy[$key] ?? null) ? $legacy[$key] : $fallback;
    }
}
