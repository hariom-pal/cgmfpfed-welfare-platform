<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\AcademicSession;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

final class AcademicSessionSeeder extends Seeder
{
    public function run(): void
    {
        AcademicSession::query()->delete();

        foreach ([
            ['2023-2024', '2023-08-01', '2024-07-31', false],
            ['2024-2025', '2024-08-01', '2025-07-31', false],
            ['2025-2026', '2025-08-01', '2026-07-31', true],
        ] as $index => [$name, $startDate, $endDate, $isActive]) {
            AcademicSession::query()->create([
                'id' => $index + 1,
                'uuid' => (string) Str::uuid(),
                'name' => $name,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'is_active' => $isActive,
                'created_by' => null,
                'updated_by' => null,
                'deleted_by' => null,
            ]);
        }
    }
}
