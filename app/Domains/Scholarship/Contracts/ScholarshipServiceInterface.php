<?php

declare(strict_types=1);

namespace App\Domains\Scholarship\Contracts;

use App\Models\ScholarshipApplication;
use App\Models\ScholarshipWorkflowBatch;
use App\Models\User;

interface ScholarshipServiceInterface
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function createDraft(array $data, User $user): ScholarshipApplication;

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateDraft(ScholarshipApplication $application, array $data, User $user): ScholarshipApplication;

    public function submit(ScholarshipApplication $application, User $user): ScholarshipApplication;

    /**
     * @param  array<string, mixed>  $data
     */
    public function resubmit(ScholarshipApplication $application, array $data, User $user): ScholarshipApplication;

    public function transition(ScholarshipApplication $application, string $action, ?string $remarks, User $user): ScholarshipApplication;

    /**
     * @param  array<int, int>  $applicationIds
     */
    public function createIcBatch(array $applicationIds, User $user, ?string $momFilePath = null, ?string $remarks = null): ScholarshipWorkflowBatch;

    /**
     * @param  array<int, int>  $applicationIds
     */
    public function createPaymentBatch(array $applicationIds, User $user, ?string $remarks = null): ScholarshipWorkflowBatch;

    public function recordPaymentResult(ScholarshipApplication $application, bool $success, ?string $reference, ?string $failureReason, User $user): ScholarshipApplication;
}
