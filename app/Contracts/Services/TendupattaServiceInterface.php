<?php

declare(strict_types=1);

namespace App\Contracts\Services;

interface TendupattaServiceInterface
{
    /**
     * @return array{available: bool, source: string, collections: array<int, array<string, mixed>>}
     */
    public function fetchCollections(string $sangrahakCardNumber): array;
}
