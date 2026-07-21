<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scholarship_applications', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();
            $table->string('application_number')->nullable()->unique();
            $table->foreignId('applicant_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('academic_session_id')->constrained('academic_sessions')->cascadeOnDelete();
            $table->foreignId('scheme_id')->constrained('schemes')->restrictOnDelete();
            $table->unsignedInteger('status')->default(0)->index();
            $table->string('status_label')->default('Draft');
            $table->string('current_stage', 40)->default('draft')->index();
            $table->boolean('is_draft')->default(true)->index();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('legacy_application_id')->nullable()->index();
            $table->unsignedInteger('district_id')->nullable()->index();
            $table->unsignedInteger('district_union_id')->nullable()->index();
            $table->unsignedInteger('samiti_id')->nullable()->index();
            $table->unsignedInteger('phad_id')->nullable()->index();
            $table->string('tendupatta_data_source', 20)->default('MANUAL');
            $table->timestamp('tendupatta_verified_at')->nullable();
            $table->foreignId('tendupatta_verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('student_aadhaar', 12);
            $table->string('aadhaar_verified_student_name');
            $table->string('student_name');
            $table->string('gender', 20)->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('mobile', 20)->nullable();
            $table->text('address')->nullable();
            $table->string('pincode', 6)->nullable();
            $table->string('block_code', 30)->nullable()->index();
            $table->string('area', 20)->nullable();
            $table->string('gram_panchayat_code', 30)->nullable()->index();
            $table->string('village_code', 30)->nullable()->index();
            $table->string('city_code', 30)->nullable()->index();
            $table->string('ward_code', 30)->nullable()->index();
            $table->string('ward_number', 30)->nullable();
            $table->string('class', 20)->nullable();
            $table->string('school_college_name')->nullable();
            $table->string('board_university')->nullable();
            $table->string('roll_number')->nullable();
            $table->decimal('marks_obtained', 10, 2)->nullable();
            $table->decimal('maximum_marks', 10, 2)->nullable();
            $table->decimal('percentage', 5, 2)->nullable();
            $table->string('course_name')->nullable();
            $table->unsignedSmallInteger('course_duration')->nullable();
            $table->string('institution_name')->nullable();
            $table->unsignedSmallInteger('admission_year')->nullable();
            $table->string('first_year_session', 20)->nullable();
            $table->string('scholarship_session', 20)->nullable();
            $table->unsignedSmallInteger('current_year_of_study')->nullable();
            $table->string('sangrahak_card_number')->nullable();
            $table->string('head_of_family_aadhaar', 12)->nullable();
            $table->string('head_of_family_name')->nullable();
            $table->string('head_of_family_father_or_husband_name')->nullable();
            $table->string('head_of_family_gender', 20)->nullable();
            $table->date('head_of_family_date_of_birth')->nullable();
            $table->string('student_bank_account_number')->nullable()->index();
            $table->string('student_bank_ifsc', 20)->nullable();
            $table->string('student_bank_name')->nullable();
            $table->string('student_bank_branch')->nullable();
            $table->string('student_bank_account_holder_name')->nullable();
            $table->decimal('amount', 12, 2)->default(0);
            $table->string('payment_status', 30)->nullable()->index();
            $table->string('payment_reference_id')->nullable();
            $table->text('payment_failure_reason')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['scheme_id', 'academic_session_id']);
            $table->index(['current_stage', 'status']);
        });

        Schema::create('scholarship_application_documents', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('scholarship_application_id');
            $table->string('document_type');
            $table->string('file_path')->nullable();
            $table->string('source', 20)->default('MANUAL');
            $table->boolean('is_verified')->default(false);
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->foreign('scholarship_application_id', 'sch_app_docs_app_fk')->references('id')->on('scholarship_applications')->cascadeOnDelete();
            $table->unique(['scholarship_application_id', 'document_type'], 'sch_app_doc_type_unique');
        });

        Schema::create('scholarship_tendupatta_collections', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('scholarship_application_id');
            $table->string('collection_year', 9);
            $table->decimal('quantity_gaddi', 10, 2)->default(0);
            $table->string('data_source', 20)->default('MANUAL');
            $table->boolean('is_verified')->default(false);
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->foreign('scholarship_application_id', 'sch_tendu_app_fk')->references('id')->on('scholarship_applications')->cascadeOnDelete();
            $table->unique(['scholarship_application_id', 'collection_year'], 'sch_app_tendu_year_unique');
        });

        Schema::create('scholarship_application_audits', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('scholarship_application_id');
            $table->unsignedInteger('from_status')->nullable();
            $table->unsignedInteger('to_status')->nullable();
            $table->string('action', 60);
            $table->string('stage', 40)->nullable();
            $table->text('remarks')->nullable();
            $table->foreignId('acted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('acted_at')->useCurrent();
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->foreign('scholarship_application_id', 'sch_audits_app_fk')->references('id')->on('scholarship_applications')->cascadeOnDelete();
            $table->index(['action', 'stage']);
        });

        Schema::create('scholarship_workflow_batches', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();
            $table->string('batch_number')->unique();
            $table->string('type', 30)->index();
            $table->string('status', 30)->default('DRAFT')->index();
            $table->date('meeting_date')->nullable();
            $table->string('financial_year', 9)->nullable();
            $table->string('mom_file_path')->nullable();
            $table->text('remarks')->nullable();
            $table->unsignedInteger('total_applications')->default(0);
            $table->decimal('total_amount', 14, 2)->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('finalized_at')->nullable();
            $table->timestamps();
        });

        Schema::create('scholarship_batch_applications', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('scholarship_workflow_batch_id');
            $table->unsignedBigInteger('scholarship_application_id');
            $table->decimal('amount', 12, 2)->default(0);
            $table->string('payment_status', 30)->nullable();
            $table->text('payment_failure_reason')->nullable();
            $table->timestamps();

            $table->foreign('scholarship_workflow_batch_id', 'sch_batch_apps_batch_fk')->references('id')->on('scholarship_workflow_batches')->cascadeOnDelete();
            $table->foreign('scholarship_application_id', 'sch_batch_apps_app_fk')->references('id')->on('scholarship_applications')->cascadeOnDelete();
            $table->unique(['scholarship_workflow_batch_id', 'scholarship_application_id'], 'sch_batch_app_unique');
        });

        Schema::create('scholarship_notifications', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('scholarship_application_id')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('channel', 30)->default('database');
            $table->string('subject');
            $table->text('body')->nullable();
            $table->string('status', 30)->default('queued');
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->foreign('scholarship_application_id', 'sch_notifications_app_fk')->references('id')->on('scholarship_applications')->cascadeOnDelete();
        });

        Schema::create('scholarship_wallet_transactions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('scholarship_application_id')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('transaction_type', 30);
            $table->decimal('amount', 12, 2)->default(0);
            $table->string('reference')->unique();
            $table->string('status', 30)->default('posted');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('scholarship_application_id', 'sch_wallet_app_fk')->references('id')->on('scholarship_applications')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scholarship_wallet_transactions');
        Schema::dropIfExists('scholarship_notifications');
        Schema::dropIfExists('scholarship_batch_applications');
        Schema::dropIfExists('scholarship_workflow_batches');
        Schema::dropIfExists('scholarship_application_audits');
        Schema::dropIfExists('scholarship_tendupatta_collections');
        Schema::dropIfExists('scholarship_application_documents');
        Schema::dropIfExists('scholarship_applications');
    }
};
