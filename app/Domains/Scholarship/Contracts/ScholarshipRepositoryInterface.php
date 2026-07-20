<?php

declare(strict_types=1);

namespace App\Domains\Scholarship\Contracts;

interface ScholarshipRepositoryInterface
{
    /**
     * Persist a scholarship application.
     */
    public function save(): void;

    /**
     * Find a scholarship application by its ID.
     */
    public function findById(int $id): mixed;

    /**
     * Update an existing scholarship application.
     */
    public function update(): void;

    /**
     * Delete a scholarship application.
     */
    public function delete(): void;
}
