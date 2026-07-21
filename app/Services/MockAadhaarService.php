<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Services\AadhaarServiceInterface;

class MockAadhaarService implements AadhaarServiceInterface
{
    public function verifyStudent(string $aadhaar, string $studentName): array
    {
        return [
            'verified' => preg_match('/^\d{12}$/', $aadhaar) === 1 && trim($studentName) !== '',
            'aadhaar' => $aadhaar,
            'name' => trim($studentName),
            'source' => 'MOCK_AADHAAR',
        ];
    }
}
