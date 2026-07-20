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
        AcademicSession::updateOrCreate(
            [
                'name' => '2026-27',
            ],
            [
                'uuid' => (string) Str::uuid(),
                'start_date' => '2026-04-01',
                'end_date' => '2027-03-31',
                'is_active' => true,
                'created_by' => null,
                'updated_by' => null,
                'deleted_by' => null,
            ]
        );
    }
}
