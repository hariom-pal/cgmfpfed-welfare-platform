<?php

declare(strict_types=1);

namespace App\Models;

use App\Domains\Scholarship\Enums\ApplicationState;
use App\Domains\Scholarship\Enums\ApprovalState;
use App\Domains\Scholarship\Enums\PaymentState;
use App\Domains\Scholarship\Enums\ScholarshipApplicationStatus;
use App\Domains\Scholarship\Enums\SubmissionState;
use App\Domains\Scholarship\Enums\WorkflowStage;
use App\Domains\Scholarship\Enums\WorkflowState;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class ScholarshipApplication extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'application_number',
        'applicant_user_id',
        'legacy_added_by',
        'academic_session_id',
        'scholarship_session_id',
        'scheme_id',
        'status',
        'status_label',
        'current_stage',
        'application_state',
        'submission_state',
        'workflow_state',
        'workflow_stage',
        'approval_state',
        'payment_state',
        'is_draft',
        'submitted_at',
        'submitted_by',
        'entered_workflow_at',
        'returned_at',
        'rejected_at',
        'completed_at',
        'wallet_paid_at',
        'legacy_application_id',
        'district_id',
        'district_union_id',
        'samiti_id',
        'phad_id',
        'block_id',
        'gram_panchayat_id',
        'village_id',
        'city_id',
        'ward_id',
        'tendupatta_data_source',
        'tendupatta_verified_at',
        'tendupatta_verified_by',
        'student_aadhaar',
        'aadhaar_verified_student_name',
        'student_name',
        'gender',
        'date_of_birth',
        'mobile',
        'address',
        'pincode',
        'block_code',
        'area',
        'gram_panchayat_code',
        'village_code',
        'city_code',
        'ward_code',
        'ward_number',
        'class',
        'school_college_name',
        'board_university',
        'roll_number',
        'marks_obtained',
        'maximum_marks',
        'percentage',
        'course_name',
        'course_duration',
        'institution_name',
        'admission_year',
        'first_year_session',
        'scholarship_session',
        'current_year_of_study',
        'sangrahak_card_number',
        'head_of_family_aadhaar',
        'head_of_family_name',
        'head_of_family_father_or_husband_name',
        'head_of_family_gender',
        'head_of_family_date_of_birth',
        'student_bank_account_number',
        'student_bank_ifsc',
        'student_bank_name',
        'student_bank_branch',
        'student_bank_account_holder_name',
        'amount',
        'payment_status',
        'payment_reference_id',
        'payment_failure_reason',
        'paid_at',
        'metadata',
        'created_by',
        'updated_by',
    ];

    /**
     * @return BelongsTo<AcademicSession, $this>
     */
    public function academicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class);
    }

    public function scholarshipSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class, 'scholarship_session_id');
    }

    /**
     * @return BelongsTo<Scheme, $this>
     */
    public function scheme(): BelongsTo
    {
        return $this->belongsTo(Scheme::class);
    }

    /**
     * @return BelongsTo<District, $this>
     */
    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    /**
     * @return BelongsTo<DistrictUnion, $this>
     */
    public function districtUnion(): BelongsTo
    {
        return $this->belongsTo(DistrictUnion::class);
    }

    /**
     * @return BelongsTo<Samiti, $this>
     */
    public function samiti(): BelongsTo
    {
        return $this->belongsTo(Samiti::class);
    }

    /**
     * @return BelongsTo<Phad, $this>
     */
    public function phad(): BelongsTo
    {
        return $this->belongsTo(Phad::class);
    }

    public function block(): BelongsTo
    {
        return $this->belongsTo(Block::class);
    }

    public function gramPanchayat(): BelongsTo
    {
        return $this->belongsTo(GramPanchayat::class);
    }

    public function village(): BelongsTo
    {
        return $this->belongsTo(Village::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function ward(): BelongsTo
    {
        return $this->belongsTo(Ward::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function applicant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'applicant_user_id');
    }

    /**
     * Resolves the submitting VLE independently of `applicant_user_id` (which is never
     * backfilled): prefers the linked `applicant` relation when set, otherwise looks up
     * whichever Laravel user has since been provisioned with a matching `csc_id` for the
     * legacy `added_by` CSC ID recorded on this application. Returns null only when neither
     * is available — the caller should still always have `legacy_added_by` to display.
     */
    public function resolveLegacyVleUser(): ?User
    {
        if ($this->relationLoaded('applicant') && $this->applicant !== null) {
            return $this->applicant;
        }

        if ($this->applicant_user_id !== null) {
            return $this->applicant()->first();
        }

        if ($this->legacy_added_by === null || $this->legacy_added_by === '') {
            return null;
        }

        return User::query()->where('csc_id', $this->legacy_added_by)->first();
    }

    /**
     * @return HasMany<ScholarshipApplicationAudit, $this>
     */
    public function audits(): HasMany
    {
        return $this->hasMany(ScholarshipApplicationAudit::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ScholarshipApplicationDocument::class)
            ->orderBy('document_type')
            ->orderByDesc('version');
    }

    public function currentDocuments(): HasMany
    {
        return $this->hasMany(ScholarshipApplicationDocument::class)
            ->where('is_current', true)
            ->orderBy('document_type');
    }

    public function tendupattaCollections(): HasMany
    {
        return $this->hasMany(ScholarshipTendupattaCollection::class);
    }

    public function walletTransactions(): HasMany
    {
        return $this->hasMany(ScholarshipWalletTransaction::class);
    }

    /**
     * @return HasMany<ScholarshipWorkflowTransition, $this>
     */
    public function workflowTransitions(): HasMany
    {
        return $this->hasMany(ScholarshipWorkflowTransition::class);
    }

    /**
     * @return HasOne<ScholarshipWorkflowTransition, $this>
     */
    public function latestWorkflowTransition(): HasOne
    {
        return $this->hasOne(ScholarshipWorkflowTransition::class)
            ->latestOfMany('acted_at');
    }

    /**
     * @return HasOne<ScholarshipApplicationAudit, $this>
     */
    public function latestAudit(): HasOne
    {
        return $this->hasOne(ScholarshipApplicationAudit::class)
            ->latestOfMany('acted_at');
    }

    /**
     * @return HasOne<ScholarshipWalletTransaction, $this>
     */
    public function latestWalletTransaction(): HasOne
    {
        return $this->hasOne(ScholarshipWalletTransaction::class)
            ->latestOfMany('created_at');
    }

    public function paymentAttempts(): HasMany
    {
        return $this->hasMany(ScholarshipPaymentAttempt::class);
    }

    public function batchApplications(): HasMany
    {
        return $this->hasMany(ScholarshipBatchApplication::class);
    }

    public function getStatusEnumAttribute(): ?ScholarshipApplicationStatus
    {
        return ScholarshipApplicationStatus::tryFrom((int) $this->status);
    }

    protected function casts(): array
    {
        return [
            'status' => 'integer',
            'scholarship_session_id' => 'integer',
            'application_state' => ApplicationState::class,
            'submission_state' => SubmissionState::class,
            'workflow_state' => WorkflowState::class,
            'workflow_stage' => WorkflowStage::class,
            'approval_state' => ApprovalState::class,
            'payment_state' => PaymentState::class,
            'is_draft' => 'boolean',
            'submitted_at' => 'datetime',
            'entered_workflow_at' => 'datetime',
            'returned_at' => 'datetime',
            'rejected_at' => 'datetime',
            'completed_at' => 'datetime',
            'wallet_paid_at' => 'datetime',
            'tendupatta_verified_at' => 'datetime',
            'district_id' => 'integer',
            'district_union_id' => 'integer',
            'samiti_id' => 'integer',
            'phad_id' => 'integer',
            'block_id' => 'integer',
            'gram_panchayat_id' => 'integer',
            'village_id' => 'integer',
            'city_id' => 'integer',
            'ward_id' => 'integer',
            'date_of_birth' => 'date',
            'head_of_family_date_of_birth' => 'date',
            'marks_obtained' => 'decimal:2',
            'maximum_marks' => 'decimal:2',
            'percentage' => 'decimal:2',
            'course_duration' => 'integer',
            'admission_year' => 'integer',
            'current_year_of_study' => 'integer',
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
            'metadata' => 'array',
        ];
    }
}
