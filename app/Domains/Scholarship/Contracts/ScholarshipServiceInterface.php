<?php

declare(strict_types=1);

namespace App\Domains\Scholarship\Contracts;

interface ScholarshipServiceInterface
{
    /**
     * Submit a new scholarship application.
     */
    public function submit(): void;

    /**
     * Update an existing scholarship application.
     */
    public function update(): void;

    /**
     * Verify a scholarship application.
     */
    public function verify(): void;

    /**
     * Approve a scholarship application.
     */
    public function approve(): void;

    /**
     * Reject a scholarship application.
     */
    public function reject(): void;

    /**
     * Resubmit a rejected scholarship application.
     */
    public function resubmit(): void;
}
