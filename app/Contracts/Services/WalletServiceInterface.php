<?php

declare(strict_types=1);

namespace App\Contracts\Services;

use App\Models\ScholarshipApplication;
use App\Models\ScholarshipWalletTransaction;
use App\Models\User;

interface WalletServiceInterface
{
    public function initiateApplicationFee(ScholarshipApplication $application, User $user): ScholarshipWalletTransaction;

    /**
     * @param  array<string, mixed>  $response
     */
    public function completeApplicationFee(ScholarshipApplication $application, array $response, User $user): ScholarshipWalletTransaction;

    /**
     * @param  array<string, mixed>  $response
     */
    public function failApplicationFee(ScholarshipApplication $application, array $response, User $user): ScholarshipWalletTransaction;
}
