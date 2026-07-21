<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Services\TendupattaServiceInterface;

class MockTendupattaService implements TendupattaServiceInterface
{
    public function fetchCollections(string $sangrahakCardNumber): array
    {
        return [
            'available' => false,
            'source' => 'MANUAL',
            'collections' => [],
        ];
    }
}
