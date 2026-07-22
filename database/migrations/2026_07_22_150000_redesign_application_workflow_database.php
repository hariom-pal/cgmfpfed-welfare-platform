<?php

declare(strict_types=1);

use App\Domains\Scholarship\Enums\ScholarshipApplicationStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scholarship_applications', function (Blueprint $table): void {
            if (! Schema::hasColumn('scholarship_applications', 'application_state')) {
                $table->string('application_state', 40)->default('draft')->after('current_stage')->index();
            }
            if (! Schema::hasColumn('scholarship_applications', 'submission_state')) {
                $table->string('submission_state', 40)->default('draft')->after('application_state')->index();
            }
            if (! Schema::hasColumn('scholarship_applications', 'workflow_state')) {
                $table->string('workflow_state', 80)->default('pending_at_vle')->after('submission_state')->index();
            }
            if (! Schema::hasColumn('scholarship_applications', 'workflow_stage')) {
                $table->string('workflow_stage', 40)->default('vle')->after('workflow_state')->index();
            }
            if (! Schema::hasColumn('scholarship_applications', 'approval_state')) {
                $table->string('approval_state', 40)->default('pending')->after('workflow_stage')->index();
            }
            if (! Schema::hasColumn('scholarship_applications', 'payment_state')) {
                $table->string('payment_state', 40)->default('wallet_not_started')->after('approval_state')->index();
            }
            if (! Schema::hasColumn('scholarship_applications', 'entered_workflow_at')) {
                $table->timestamp('entered_workflow_at')->nullable()->after('submitted_by')->index();
            }
            if (! Schema::hasColumn('scholarship_applications', 'returned_at')) {
                $table->timestamp('returned_at')->nullable()->after('entered_workflow_at');
            }
            if (! Schema::hasColumn('scholarship_applications', 'rejected_at')) {
                $table->timestamp('rejected_at')->nullable()->after('returned_at');
            }
            if (! Schema::hasColumn('scholarship_applications', 'completed_at')) {
                $table->timestamp('completed_at')->nullable()->after('rejected_at');
            }
        });

        Schema::dropIfExists('scholarship_payment_attempts');
        Schema::dropIfExists('scholarship_workflow_transitions');

        Schema::create('scholarship_workflow_transitions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('scholarship_application_id');
            $table->string('from_application_state', 40)->nullable();
            $table->string('to_application_state', 40);
            $table->string('from_workflow_state', 80)->nullable();
            $table->string('to_workflow_state', 80);
            $table->string('from_workflow_stage', 40)->nullable();
            $table->string('to_workflow_stage', 40);
            $table->string('from_payment_state', 40)->nullable();
            $table->string('to_payment_state', 40);
            $table->string('from_approval_state', 40)->nullable();
            $table->string('to_approval_state', 40);
            $table->string('action', 80);
            $table->text('remarks')->nullable();
            $table->foreignId('acted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('acted_by_role', 80)->nullable();
            $table->timestamp('acted_at')->useCurrent();
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->foreign('scholarship_application_id', 'sch_wf_trans_app_fk')->references('id')->on('scholarship_applications')->cascadeOnDelete();
            $table->index(['to_workflow_stage', 'to_workflow_state'], 'sch_workflow_transitions_queue_idx');
            $table->index(['action', 'acted_at'], 'sch_workflow_transitions_action_idx');
        });

        Schema::create('scholarship_payment_attempts', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('scholarship_application_id');
            $table->unsignedBigInteger('wallet_transaction_id')->nullable();
            $table->string('payment_purpose', 40)->default('vle_submission_fee')->index();
            $table->string('payment_channel', 40)->default('csc_wallet');
            $table->string('transaction_number')->nullable()->index();
            $table->decimal('amount', 12, 2)->default(0);
            $table->string('payment_state', 40)->default('pending')->index();
            $table->timestamp('payment_requested_at')->nullable();
            $table->timestamp('payment_completed_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->unsignedInteger('attempt_number')->default(1);
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign('scholarship_application_id', 'sch_pay_attempt_app_fk')->references('id')->on('scholarship_applications')->cascadeOnDelete();
            $table->foreign('wallet_transaction_id', 'sch_pay_attempt_wallet_fk')->references('id')->on('scholarship_wallet_transactions')->nullOnDelete();
            $table->index(['scholarship_application_id', 'payment_purpose', 'attempt_number'], 'sch_payment_attempt_app_purpose_idx');
        });

        $this->backfillApplicationStates();
    }

    public function down(): void
    {
        Schema::dropIfExists('scholarship_payment_attempts');
        Schema::dropIfExists('scholarship_workflow_transitions');

        Schema::table('scholarship_applications', function (Blueprint $table): void {
            $columns = [
                'application_state',
                'submission_state',
                'workflow_state',
                'workflow_stage',
                'approval_state',
                'payment_state',
                'entered_workflow_at',
                'returned_at',
                'rejected_at',
                'completed_at',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('scholarship_applications', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    private function backfillApplicationStates(): void
    {
        DB::table('scholarship_applications')
            ->select(['id', 'status', 'is_draft', 'submitted_at', 'wallet_paid_at', 'payment_status', 'paid_at'])
            ->orderBy('id')
            ->chunkById(500, function ($applications): void {
                foreach ($applications as $application) {
                    $status = ScholarshipApplicationStatus::tryFrom((int) $application->status);
                    $workflowState = $status?->workflowState() ?? 'pending_at_vle';
                    $workflowStage = $status?->workflowStage() ?? 'vle';
                    $paymentState = $this->paymentState($application, $status);
                    $applicationState = $this->applicationState($application, $status, $paymentState);

                    DB::table('scholarship_applications')
                        ->where('id', $application->id)
                        ->update([
                            'application_state' => $applicationState,
                            'submission_state' => $this->submissionState($application, $applicationState),
                            'workflow_state' => $workflowState,
                            'workflow_stage' => $workflowStage,
                            'approval_state' => $status?->approvalState() ?? 'pending',
                            'payment_state' => $paymentState,
                            'entered_workflow_at' => $application->submitted_at,
                            'completed_at' => $status?->isCompleted() === true ? ($application->paid_at ?: now()) : null,
                        ]);
                }
            });
    }

    private function applicationState(object $application, ?ScholarshipApplicationStatus $status, string $paymentState): string
    {
        if ((bool) $application->is_draft || $application->submitted_at === null) {
            return $paymentState === 'wallet_success' ? 'submitted' : 'created';
        }

        if ($status?->isCompleted() === true) {
            return 'completed';
        }

        if ($status?->approvalState() === 'rejected') {
            return 'rejected';
        }

        if ($status?->approvalState() === 'returned_for_correction') {
            return 'returned_for_correction';
        }

        return 'in_workflow';
    }

    private function submissionState(object $application, string $applicationState): string
    {
        if ((bool) $application->is_draft && $application->wallet_paid_at === null) {
            return 'draft';
        }

        return in_array($applicationState, ['created', 'submitted'], true) && $application->submitted_at === null
            ? 'wallet_pending'
            : 'submitted';
    }

    private function paymentState(object $application, ?ScholarshipApplicationStatus $status): string
    {
        if ($application->wallet_paid_at !== null) {
            return 'wallet_success';
        }

        if ($application->payment_status === 'success' || $status?->isCompleted() === true) {
            return 'beneficiary_payment_success';
        }

        if ($application->payment_status === 'failed' || $status?->isPaymentFailed() === true) {
            return 'beneficiary_payment_failed';
        }

        if ($application->payment_status === 'submitted') {
            return 'beneficiary_payment_submitted';
        }

        return 'wallet_not_started';
    }
};
