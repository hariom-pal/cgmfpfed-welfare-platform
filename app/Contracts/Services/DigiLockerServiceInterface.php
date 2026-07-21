<?php

declare(strict_types=1);

namespace App\Contracts\Services;

interface DigiLockerServiceInterface
{
    /**
     * @param  array<string, mixed>  $context
     * @return array{available: bool, source: string, documents: array<int, array<string, mixed>>}
     */
    public function fetchStudentDocuments(string $studentAadhaar, array $context = []): array;
}
