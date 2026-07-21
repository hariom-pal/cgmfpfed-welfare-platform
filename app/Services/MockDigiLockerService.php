<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Services\DigiLockerServiceInterface;

class MockDigiLockerService implements DigiLockerServiceInterface
{
    public function fetchStudentDocuments(string $studentAadhaar, array $context = []): array
    {
        return [
            'available' => false,
            'source' => 'MANUAL',
            'documents' => [],
        ];
    }
}
