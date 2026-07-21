<?php

declare(strict_types=1);

namespace App\Contracts\Services;

interface AadhaarServiceInterface
{
    /**
     * @return array{verified: bool, aadhaar: string, name: string, source: string}
     */
    public function verifyStudent(string $aadhaar, string $studentName): array;
}
