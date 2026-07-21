<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domains\Scholarship\Enums\ScholarshipApplicationStatus;
use App\Support\LegacyScholarshipSql;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

final class CompleteLegacyDataMigrationSeeder extends Seeder
{
    /**
     * @var array<string, int>
     */
    private array $applicationIds = [];

    /**
     * @var array<string, int>
     */
    private array $userIds = [];

    /**
     * @var array<string, array{count: int, amount: float}>
     */
    private array $applicationBatchSummaries = [];

    public function run(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql' || ! Schema::hasTable($this->source('application'))) {
            return;
        }

        try {
            Schema::disableForeignKeyConstraints();

            $this->clearDestinations();
            $this->archiveAllSourceTables();
            $this->migrateUsers();
            $this->loadUserMap();
            $this->migrateApplications();
            $this->loadApplicationMap();
            $this->migrateDocuments();
            $this->migrateTendupattaVerifications();
            $this->migrateAudits();
            $this->loadApplicationBatchSummaries();
            $this->migrateIcBatches();
            $this->migratePaymentBatches();
            $this->migrateWalletTransactions();
            $this->dropSourceTables();
        } finally {
            Schema::enableForeignKeyConstraints();
        }
    }

    private function clearDestinations(): void
    {
        foreach ([
            'scholarship_wallet_transactions',
            'scholarship_notifications',
            'scholarship_batch_applications',
            'scholarship_workflow_batches',
            'scholarship_application_audits',
            'scholarship_tendupatta_collections',
            'scholarship_application_documents',
            'scholarship_applications',
            'source_data_archives',
        ] as $table) {
            DB::table($table)->truncate();
        }
    }

    private function archiveAllSourceTables(): void
    {
        foreach (LegacyScholarshipSql::tableNames(LegacyScholarshipSql::read()) as $table) {
            $sourceTable = $this->source($table);
            if (! Schema::hasTable($sourceTable)) {
                continue;
            }

            $columns = DB::select("SHOW COLUMNS FROM `{$sourceTable}`");
            $columnNames = array_map(fn (object $column): string => (string) $column->Field, $columns);
            $primaryColumn = in_array('id', $columnNames, true) ? 'id' : null;
            $jsonPairs = collect($columns)->flatMap(function (object $column): array {
                $name = (string) $column->Field;
                $type = strtolower((string) $column->Type);
                $value = match (true) {
                    str_contains($type, 'binary'), str_contains($type, 'blob') => "TO_BASE64(`{$name}`)",
                    str_contains($type, 'date'), str_contains($type, 'time') => "NULLIF(NULLIF(CAST(`{$name}` AS CHAR), '0000-00-00'), '0000-00-00 00:00:00')",
                    default => "`{$name}`",
                };

                return [DB::getPdo()->quote($name), $value];
            })->implode(', ');

            $sourceCreated = $this->timestampExpression($columnNames, ['created_at', 'add_date', 'added_date']);
            $sourceUpdated = $this->timestampExpression($columnNames, ['updated_at', 'updated_date']);
            $sourcePrimary = $primaryColumn === null ? 'NULL' : "CAST(`{$primaryColumn}` AS CHAR)";

            DB::statement("
                INSERT INTO `source_data_archives`
                    (`source_table`, `source_primary_key`, `payload`, `source_created_at`, `source_updated_at`, `created_at`, `updated_at`)
                SELECT
                    ?,
                    {$sourcePrimary},
                    JSON_OBJECT({$jsonPairs}),
                    {$sourceCreated},
                    {$sourceUpdated},
                    NOW(),
                    NOW()
                FROM `{$sourceTable}`
            ", [$table]);
        }
    }

    private function migrateUsers(): void
    {
        if (! Schema::hasTable($this->source('users'))) {
            return;
        }

        DB::table($this->source('users'))
            ->orderBy('id')
            ->lazy(500)
            ->chunk(500)
            ->each(function ($chunk): void {
                $rows = $chunk->map(function (object $row): array {
                    $createdAt = $this->dateValue($row->add_date) ?? now();

                    return [
                        'id' => (int) $row->id,
                        'name' => $this->text($row->name, 'Migrated User '.$row->id),
                        'email' => trim((string) $row->email) !== '' ? $row->email : null,
                        'mobile' => trim((string) $row->mobile) !== '' ? $row->mobile : null,
                        'email_verified_at' => null,
                        'password' => trim((string) $row->password) !== '' ? $row->password : bcrypt(Str::random(24)),
                        'status' => in_array((string) $row->status, ['0', '1', '2'], true) ? (string) $row->status : '1',
                        'add_date' => $this->dateValue($row->add_date),
                        'user_type' => $this->nullableInt($row->user_type),
                        'district' => $this->nullableInt($row->district),
                        'circle' => $this->nullableInt($row->circle),
                        'districtunion' => $this->nullableInt($row->districtunion),
                        'samiti' => $this->nullableInt($row->samiti),
                        'reset_code' => $row->reset_code,
                        'fail_attempt' => (int) ($row->fail_attempt ?? 0),
                        'created_at' => $createdAt,
                        'updated_at' => $createdAt,
                    ];
                })->all();

                DB::table('users')->upsert(
                    $rows,
                    ['id'],
                    [
                        'name',
                        'email',
                        'mobile',
                        'password',
                        'status',
                        'add_date',
                        'user_type',
                        'district',
                        'circle',
                        'districtunion',
                        'samiti',
                        'reset_code',
                        'fail_attempt',
                        'updated_at',
                    ],
                );
            });
    }

    /**
     * @param  list<string>  $columns
     * @param  list<string>  $candidates
     */
    private function timestampExpression(array $columns, array $candidates): string
    {
        foreach ($candidates as $candidate) {
            if (in_array($candidate, $columns, true)) {
                return "NULLIF(NULLIF(CAST(`{$candidate}` AS CHAR), '0000-00-00'), '0000-00-00 00:00:00')";
            }
        }

        return 'NULL';
    }

    private function migrateApplications(): void
    {
        DB::table($this->source('application'))
            ->orderBy('id')
            ->lazy(500)
            ->chunk(500)
            ->each(function ($chunk): void {
                DB::table('scholarship_applications')->insert($chunk->map(function (object $row): array {
                    $status = ScholarshipApplicationStatus::tryFrom((int) $row->status) ?? ScholarshipApplicationStatus::Pending;
                    $createdAt = $this->dateValue($row->add_date) ?? now();
                    $sessionId = $this->academicSessionId($row->scholarship_session ?: $row->first_year_session ?: $row->passing_year);
                    $class = (string) $row->class;
                    $yearOfStudy = (int) ($row->education_year ?: 1);
                    $studentAadhaar = $this->digitsOrFallback($row->student_aadhar, $row->id);

                    return [
                        'uuid' => (string) Str::uuid(),
                        'application_number' => $row->application_id ?: 'LEGACY-'.$row->id,
                        'applicant_user_id' => $this->userIdForCsc((string) $row->added_by),
                        'academic_session_id' => $sessionId,
                        'scheme_id' => (int) $row->scheme,
                        'status' => (int) $row->status,
                        'status_label' => $status->label(),
                        'current_stage' => $status->stage(),
                        'is_draft' => $row->payment_txn_status !== '1',
                        'submitted_at' => $row->payment_txn_status === '1' ? $createdAt : null,
                        'submitted_by' => $this->userIdForCsc((string) $row->added_by),
                        'wallet_paid_at' => $row->payment_txn_status === '1' ? $createdAt : null,
                        'legacy_application_id' => (int) $row->id,
                        'district_id' => $this->nullableInt($row->district),
                        'district_union_id' => $this->nullableInt($row->districtunion),
                        'samiti_id' => $this->nullableInt($row->samitiname),
                        'phad_id' => $this->nullableInt($row->phadname),
                        'tendupatta_data_source' => 'MANUAL',
                        'student_aadhaar' => $studentAadhaar,
                        'aadhaar_verified_student_name' => $this->text($row->student_name, 'Legacy Student '.$row->id),
                        'student_name' => $this->text($row->student_name, 'Legacy Student '.$row->id),
                        'gender' => $row->gender,
                        'date_of_birth' => $row->birthdate,
                        'mobile' => $row->mobile,
                        'address' => $row->address,
                        'class' => $class,
                        'school_college_name' => $row->school_name,
                        'board_university' => $row->university_name,
                        'roll_number' => null,
                        'marks_obtained' => $this->decimal($row->marks_obtained),
                        'maximum_marks' => $this->decimal($row->total_marks),
                        'percentage' => $this->percentage($row->marks_obtained, $row->total_marks, $row->mark_percentage),
                        'course_name' => $row->course_name,
                        'institution_name' => $row->institute_name,
                        'admission_year' => $this->nullableInt($row->passing_year),
                        'current_year_of_study' => $yearOfStudy,
                        'sangrahak_card_number' => $row->sangrahak_card_number,
                        'head_of_family_aadhaar' => $this->digitsOrNull($row->father_aadhar),
                        'head_of_family_name' => $row->father_name,
                        'head_of_family_father_or_husband_name' => null,
                        'head_of_family_gender' => null,
                        'head_of_family_date_of_birth' => null,
                        'student_bank_account_number' => $this->digitsOrNull($row->accountnumber),
                        'student_bank_ifsc' => $row->ifsc !== '' ? strtoupper((string) $row->ifsc) : null,
                        'student_bank_name' => $row->bankname !== '' ? $row->bankname : null,
                        'student_bank_branch' => $row->branch !== '' ? $row->branch : null,
                        'student_bank_account_holder_name' => $row->accountname !== '' ? $row->accountname : null,
                        'amount' => $row->amount ?? $this->calculateAmount((int) $row->scheme, $class, $yearOfStudy),
                        'payment_status' => $row->paymentstatus,
                        'payment_reference_id' => $row->paymentreferenceid,
                        'payment_failure_reason' => $row->paymentfailreason ?: $row->otherreason,
                        'paid_at' => $row->paymentstatus === '1' ? $this->dateValue($row->updated_date) : null,
                        'metadata' => json_encode([
                            'legacy_sql_id' => $row->id,
                            'legacy_application_type' => $row->application_type,
                            'legacy_location' => [
                                'block' => $row->block,
                                'gram_panchayat' => $row->grampanchayat,
                                'village' => $row->village,
                                'area' => $row->area,
                                'city' => $row->city,
                                'ward' => $row->ward,
                            ],
                            'legacy_head_of_family_bank_present' => trim((string) $row->haccountnumber) !== '',
                        ], JSON_INVALID_UTF8_SUBSTITUTE),
                        'created_by' => $this->userIdForCsc((string) $row->added_by),
                        'updated_by' => $this->userIdForCsc((string) ($row->updated_by ?: $row->added_by)),
                        'created_at' => $createdAt,
                        'updated_at' => $this->dateValue($row->updated_date) ?? $createdAt,
                        'deleted_at' => null,
                    ];
                })->all());
            });
    }

    private function loadApplicationMap(): void
    {
        $this->applicationIds = DB::table('scholarship_applications')
            ->whereNotNull('application_number')
            ->pluck('id', 'application_number')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();
    }

    private function loadUserMap(): void
    {
        $this->userIds = [];

        DB::table('users')
            ->select('id', 'csc_id')
            ->orderBy('id')
            ->lazy(500)
            ->each(function (object $user): void {
                $this->userIds[(string) $user->id] = (int) $user->id;

                if ($user->csc_id !== null && $user->csc_id !== '') {
                    $this->userIds[(string) $user->csc_id] = (int) $user->id;
                }
            });
    }

    private function migrateDocuments(): void
    {
        $directColumns = ['aadharcard', 'tpcard', 'admission_copy', 'passbook', 'admission_receipt'];
        foreach ($directColumns as $column) {
            DB::table($this->source('application'))
                ->select('application_id', $column, 'added_by', 'add_date')
                ->whereNotNull($column)
                ->where($column, '!=', '')
                ->orderBy('id')
                ->lazy(500)
                ->chunk(500)
                ->each(function ($chunk) use ($column): void {
                    $this->insertDocuments($chunk->map(fn (object $row): array => [
                        'application_number' => $row->application_id,
                        'document_type' => $column,
                        'file_path' => $row->{$column},
                        'source' => 'MANUAL',
                        'created_at' => $this->dateValue($row->add_date) ?? now(),
                    ])->all());
                });
        }

        DB::table($this->source('application_files'))
            ->orderBy('id')
            ->lazy(500)
            ->chunk(500)
            ->each(function ($chunk): void {
                $this->insertDocuments($chunk->map(fn (object $row): array => [
                    'application_number' => $row->application_id,
                    'document_type' => $row->filetype,
                    'file_path' => $row->filepath,
                    'source' => 'MANUAL',
                    'created_at' => $this->dateValue($row->add_date) ?? now(),
                ])->all());
            });
    }

    /**
     * @param  list<array<string, mixed>>  $documents
     */
    private function insertDocuments(array $documents): void
    {
        $rows = [];
        foreach ($documents as $document) {
            $applicationId = $this->applicationId((string) $document['application_number']);
            if ($applicationId === null || trim((string) $document['file_path']) === '') {
                continue;
            }

            $rows[] = [
                'scholarship_application_id' => $applicationId,
                'document_type' => (string) $document['document_type'],
                'file_path' => (string) $document['file_path'],
                'source' => (string) $document['source'],
                'is_verified' => false,
                'verified_by' => null,
                'verified_at' => null,
                'remarks' => null,
                'created_at' => $document['created_at'],
                'updated_at' => $document['created_at'],
            ];
        }

        if ($rows === []) {
            return;
        }

        DB::table('scholarship_application_documents')->upsert(
            $rows,
            ['scholarship_application_id', 'document_type'],
            ['file_path', 'source', 'updated_at'],
        );
    }

    private function migrateTendupattaVerifications(): void
    {
        DB::table($this->source('application_verify'))
            ->orderBy('id')
            ->lazy(500)
            ->chunk(500)
            ->each(function ($chunk): void {
                $rows = [];
                foreach ($chunk as $row) {
                    $applicationId = $this->applicationId((string) $row->application_id);
                    if ($applicationId === null) {
                        continue;
                    }

                    foreach ([1, 2, 3] as $index) {
                        $year = $row->{'collection_year'.$index};
                        if ($year === null || $year === '') {
                            continue;
                        }

                        $rows[] = [
                            'scholarship_application_id' => $applicationId,
                            'collection_year' => (string) $year,
                            'quantity_gaddi' => (float) ($row->{'collection'.$index} ?: 0),
                            'data_source' => 'MANUAL',
                            'is_verified' => true,
                            'verified_by' => $this->userIdForCsc((string) $row->updated_by),
                            'verified_at' => now(),
                            'remarks' => trim((string) $row->reason) ?: null,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }
                }

                if ($rows !== []) {
                    DB::table('scholarship_tendupatta_collections')->upsert(
                        $rows,
                        ['scholarship_application_id', 'collection_year'],
                        ['quantity_gaddi', 'is_verified', 'verified_by', 'verified_at', 'remarks', 'updated_at'],
                    );
                }
            });
    }

    private function migrateAudits(): void
    {
        DB::table($this->source('application_status'))
            ->orderBy('id')
            ->lazy(500)
            ->chunk(500)
            ->each(function ($chunk): void {
                $rows = [];
                foreach ($chunk as $row) {
                    $applicationId = $this->applicationId((string) $row->application_id);
                    if ($applicationId === null) {
                        continue;
                    }

                    $status = ScholarshipApplicationStatus::tryFrom((int) $row->status);
                    $actedAt = $this->dateValue($row->verificationdate) ?? now();
                    $rows[] = [
                        'scholarship_application_id' => $applicationId,
                        'from_status' => null,
                        'to_status' => (int) $row->status,
                        'action' => 'legacy_status_migrated',
                        'stage' => $status?->stage() ?? 'legacy',
                        'remarks' => $row->comments,
                        'acted_by' => $this->userIdForCsc((string) $row->updated_by),
                        'acted_at' => $actedAt,
                        'payload' => json_encode([
                            'legacy_status_id' => $row->id,
                            'verificationofficername' => $row->verificationofficername,
                            'verifyingofficernumber' => $row->verifyingofficernumber,
                            'verificationofficerpost' => $row->verificationofficerpost,
                            'verificationofficerrange' => $row->verificationofficerrange,
                        ], JSON_INVALID_UTF8_SUBSTITUTE),
                        'created_at' => $actedAt,
                        'updated_at' => $actedAt,
                    ];
                }

                if ($rows !== []) {
                    DB::table('scholarship_application_audits')->insert($rows);
                }
            });
    }

    private function migrateIcBatches(): void
    {
        DB::table($this->source('application_batch'))
            ->orderBy('id')
            ->lazy(500)
            ->chunk(500)
            ->each(function ($chunk): void {
                DB::table('scholarship_workflow_batches')->insert($chunk->map(fn (object $row): array => [
                    'uuid' => (string) Str::uuid(),
                    'batch_number' => 'LEGACY-IC-'.$row->batchid,
                    'type' => 'IC',
                    'status' => $row->orderid ? 'FINALIZED' : 'SUBMITTED',
                    'meeting_date' => $row->verificationdate,
                    'financial_year' => null,
                    'mom_file_path' => $row->momfile,
                    'remarks' => 'Migrated legacy IC/application batch. Order ID: '.$row->orderid,
                    'total_applications' => $this->applicationBatchSummaries[(string) $row->batchid]['count'] ?? 0,
                    'total_amount' => $this->applicationBatchSummaries[(string) $row->batchid]['amount'] ?? 0,
                    'created_by' => $this->userIdForCsc((string) $row->added_by),
                    'submitted_at' => $this->dateValue($row->added_date),
                    'finalized_at' => $this->dateValue($row->verificationdate),
                    'created_at' => $this->dateValue($row->added_date) ?? now(),
                    'updated_at' => $this->dateValue($row->verificationdate) ?? $this->dateValue($row->added_date) ?? now(),
                ])->all());
            });
    }

    private function loadApplicationBatchSummaries(): void
    {
        $this->applicationBatchSummaries = [];

        DB::table($this->source('application'))
            ->select('batchid', DB::raw('COUNT(*) as aggregate'), DB::raw('SUM(COALESCE(amount, 0)) as total_amount'))
            ->whereNotNull('batchid')
            ->where('batchid', '!=', '')
            ->groupBy('batchid')
            ->orderBy('batchid')
            ->lazy(500)
            ->each(function (object $row): void {
                $this->applicationBatchSummaries[(string) $row->batchid] = [
                    'count' => (int) $row->aggregate,
                    'amount' => (float) $row->total_amount,
                ];
            });
    }

    private function migratePaymentBatches(): void
    {
        DB::table($this->source('payment_batch'))
            ->orderBy('id')
            ->lazy(200)
            ->chunk(200)
            ->each(function ($chunk): void {
                DB::table('scholarship_workflow_batches')->insert($chunk->map(fn (object $row): array => [
                    'id' => 100000 + (int) $row->id,
                    'uuid' => (string) Str::uuid(),
                    'batch_number' => 'LEGACY-PAY-'.$row->id,
                    'type' => 'PAYMENT',
                    'status' => (int) $row->status === 1 ? 'PROCESSED' : 'SUBMITTED',
                    'meeting_date' => null,
                    'financial_year' => null,
                    'mom_file_path' => $row->file_name,
                    'remarks' => 'Migrated legacy payment batch.',
                    'total_applications' => (int) $row->total_application,
                    'total_amount' => (float) $row->total_amount,
                    'created_by' => $this->userIdForCsc((string) $row->added_by),
                    'submitted_at' => now(),
                    'finalized_at' => (int) $row->status === 1 ? now() : null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ])->all());
            });

        DB::table($this->source('payment_batch_application'))
            ->orderBy('id')
            ->lazy(500)
            ->chunk(500)
            ->each(function ($chunk): void {
                $rows = [];
                foreach ($chunk as $row) {
                    $applicationId = $this->applicationId((string) $row->application_number);
                    if ($applicationId === null) {
                        continue;
                    }

                    $rows[] = [
                        'scholarship_workflow_batch_id' => 100000 + (int) $row->batch_id,
                        'scholarship_application_id' => $applicationId,
                        'amount' => (float) $row->amount,
                        'payment_status' => null,
                        'payment_failure_reason' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }

                if ($rows !== []) {
                    DB::table('scholarship_batch_applications')->insertOrIgnore($rows);
                }
            });
    }

    private function migrateWalletTransactions(): void
    {
        DB::table($this->source('pg_request'))
            ->orderBy('id')
            ->lazy(500)
            ->chunk(500)
            ->each(function ($chunk): void {
                $rows = [];
                foreach ($chunk as $row) {
                    $applicationId = $this->applicationId((string) $row->application_id);
                    if ($applicationId === null || $row->merchant_txn === null) {
                        continue;
                    }

                    $rows[] = [
                        'scholarship_application_id' => $applicationId,
                        'user_id' => $this->userIdForCsc((string) $row->csc_id),
                        'transaction_type' => 'application_fee',
                        'amount' => (float) $row->amount,
                        'reference' => $row->merchant_txn,
                        'status' => match ((string) $row->transaction_status) {
                            '1' => 'posted',
                            '3' => 'failed',
                            default => 'pending',
                        },
                        'metadata' => json_encode([
                            'legacy_pg_request_id' => $row->id,
                            'merchant_receipt' => $row->merchant_receipt,
                            'request' => json_decode((string) $row->request, true) ?: $row->request,
                            'reversal_txn' => $row->reversal_txn,
                        ], JSON_INVALID_UTF8_SUBSTITUTE),
                        'created_at' => $this->dateValue($row->add_date) ?? now(),
                        'updated_at' => $this->dateValue($row->add_date) ?? now(),
                    ];
                }

                if ($rows !== []) {
                    DB::table('scholarship_wallet_transactions')->insertOrIgnore($rows);
                }
            });
    }

    private function dropSourceTables(): void
    {
        foreach (array_reverse(LegacyScholarshipSql::tableNames(LegacyScholarshipSql::read())) as $table) {
            Schema::dropIfExists($this->source($table));
        }
    }

    private function source(string $table): string
    {
        return (string) config('legacy_database.table_prefix').$table;
    }

    private function applicationId(string $applicationNumber): ?int
    {
        return $this->applicationIds[$applicationNumber] ?? null;
    }

    private function userIdForCsc(string $identifier): ?int
    {
        return $this->userIds[$identifier] ?? null;
    }

    private function academicSessionId(mixed $session): int
    {
        $name = trim((string) $session);
        if ($name === '' || $name === '0') {
            $name = 'Legacy Unknown';
        }

        $id = DB::table('academic_sessions')->where('name', $name)->value('id');
        if ($id !== null) {
            return (int) $id;
        }

        $nextId = ((int) DB::table('academic_sessions')->max('id')) + 1;
        DB::table('academic_sessions')->insert([
            'id' => $nextId,
            'uuid' => (string) Str::uuid(),
            'name' => $name,
            'start_date' => now()->startOfYear()->toDateString(),
            'end_date' => now()->endOfYear()->toDateString(),
            'is_active' => false,
            'created_by' => null,
            'updated_by' => null,
            'deleted_by' => null,
            'created_at' => now(),
            'updated_at' => now(),
            'deleted_at' => null,
        ]);

        return $nextId;
    }

    private function nullableInt(mixed $value): ?int
    {
        return $value === null || $value === '' || $value === 0 || $value === '0' ? null : (int) $value;
    }

    private function decimal(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }

    private function percentage(mixed $obtained, mixed $total, mixed $legacyPercentage): ?float
    {
        $obtained = $this->decimal($obtained);
        $total = $this->decimal($total);
        if ($obtained !== null && $total !== null && $total > 0) {
            return round(($obtained / $total) * 100, 2);
        }

        return $this->decimal($legacyPercentage);
    }

    private function dateValue(mixed $value): ?string
    {
        if ($value === null || $value === '' || $value === '0000-00-00') {
            return null;
        }

        try {
            return Carbon::parse((string) $value)->toDateTimeString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function digitsOrNull(mixed $value): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $value);

        return $digits === '' ? null : $digits;
    }

    private function digitsOrFallback(mixed $value, int $id): string
    {
        $digits = $this->digitsOrNull($value);

        return $digits !== null ? substr($digits, 0, 12) : str_pad((string) $id, 12, '0', STR_PAD_LEFT);
    }

    private function text(mixed $value, string $fallback): string
    {
        $text = trim((string) $value);

        return $text === '' ? $fallback : $text;
    }

    private function calculateAmount(int $schemeId, string $class, int $yearOfStudy): int
    {
        if ($schemeId === 1) {
            return $class === '12' ? 3000 : ($class === '10' ? 2500 : 0);
        }

        if ($schemeId === 2) {
            return $class === '12' ? 25000 : ($class === '10' ? 15000 : 0);
        }

        if ($schemeId === 3) {
            return $yearOfStudy <= 1 ? 10000 : 5000;
        }

        if ($schemeId === 4) {
            return $yearOfStudy <= 1 ? 5000 : ($yearOfStudy === 2 ? 4000 : 3000);
        }

        return 0;
    }
}
