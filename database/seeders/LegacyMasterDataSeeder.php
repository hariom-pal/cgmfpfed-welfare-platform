<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

final class LegacyMasterDataSeeder extends Seeder
{
    public function run(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql' || ! Schema::hasTable('legacy_schemes')) {
            return;
        }

        try {
            Schema::disableForeignKeyConstraints();

            DB::table('scheme_documents')->delete();

            foreach (['schemes', 'districts', 'circles', 'district_unions', 'samitis', 'phads', 'academic_sessions'] as $table) {
                DB::table($table)->delete();
            }

            $this->seedSchemes();
            $this->seedDistricts();
            $this->seedCircles();
            $this->seedDistrictUnions();
            $this->seedSamitis();
            $this->seedPhads();
            $this->seedAcademicSessions();
        } finally {
            Schema::enableForeignKeyConstraints();
        }
    }

    private function seedSchemes(): void
    {
        $rows = DB::table('legacy_schemes')->orderBy('id')->get()->map(fn (object $row): array => [
            'id' => $row->id,
            'uuid' => (string) Str::uuid(),
            'code' => 'SCH-'.$row->id,
            'name' => $row->name,
            'description' => null,
            'is_active' => $row->status === '1',
            'created_by' => null,
            'updated_by' => null,
            'deleted_by' => null,
            'created_at' => $row->created_at,
            'updated_at' => $row->updated_at,
        ])->all();

        DB::table('schemes')->insert($rows);
    }

    private function seedDistricts(): void
    {
        $rows = DB::table('legacy_districts')->orderBy('id')->get()->map(fn (object $row): array => [
            'id' => $row->id,
            'uuid' => (string) Str::uuid(),
            'code' => 'DST-'.$row->district_code,
            'legacy_code' => $row->district_code,
            'name' => $row->district_name,
            'description' => null,
            'is_active' => true,
            'created_by' => null,
            'updated_by' => null,
            'deleted_by' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ])->all();

        DB::table('districts')->insert($rows);
    }

    private function seedCircles(): void
    {
        if (! Schema::hasTable('legacy_circles') || ! Schema::hasTable('circles')) {
            return;
        }

        $rows = DB::table('legacy_circles')->orderBy('id')->get()->map(fn (object $row): array => [
            'id' => $row->id,
            'uuid' => (string) Str::uuid(),
            'legacy_id' => $row->id,
            'legacy_code' => (string) $row->id,
            'name' => $row->circle_name,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ])->all();

        DB::table('circles')->insert($rows);
    }

    private function seedDistrictUnions(): void
    {
        $rows = DB::table('legacy_district_union')->orderBy('id')->get()->map(fn (object $row): array => [
            'id' => $row->id,
            'uuid' => (string) Str::uuid(),
            'legacy_id' => $row->id,
            'code' => 'DUN-'.$row->id,
            'name' => $row->union_name,
            'district_id' => $this->districtIdFromLegacyCode($row->district_code),
            'circle_id' => $this->circleIdFromLegacyId($row->circle_id),
            'legacy_district_code' => $row->district_code,
            'legacy_circle_id' => $row->circle_id,
            'description' => null,
            'is_active' => true,
            'created_by' => null,
            'updated_by' => null,
            'deleted_by' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ])->all();

        DB::table('district_unions')->insert($rows);
    }

    private function seedSamitis(): void
    {
        foreach (DB::table('legacy_samiti')->orderBy('id')->lazy(500)->chunk(500) as $chunk) {
            DB::table('samitis')->insert($chunk->map(fn (object $row): array => [
                'id' => $row->id,
                'uuid' => (string) Str::uuid(),
                'legacy_id' => $row->id,
                'code' => 'SMT-'.$row->id,
                'name' => $row->samiti_name,
                'district_id' => $this->districtIdFromLegacyCode($row->district_code),
                'district_union_id' => $row->district_union_id,
                'legacy_district_code' => $row->district_code,
                'legacy_district_union_id' => $row->district_union_id,
                'description' => null,
                'is_active' => true,
                'created_by' => null,
                'updated_by' => null,
                'deleted_by' => null,
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
            ])->all());
        }
    }

    private function seedPhads(): void
    {
        foreach (DB::table('legacy_phads')->orderBy('id')->lazy(500)->chunk(500) as $chunk) {
            DB::table('phads')->insert($chunk->map(fn (object $row): array => [
                'id' => $row->id,
                'uuid' => (string) Str::uuid(),
                'legacy_id' => $row->id,
                'legacy_code' => $row->phad_code,
                'code' => 'PHD-'.$row->phad_code.'-'.$row->id,
                'name' => $row->phad_name,
                'district_id' => $this->districtIdFromLegacyCode($row->district_code),
                'district_union_id' => $row->district_union_id,
                'samiti_id' => $row->samiti_id,
                'legacy_district_code' => $row->district_code,
                'legacy_district_union_id' => $row->district_union_id,
                'legacy_samiti_id' => $row->samiti_id,
                'description' => null,
                'is_active' => true,
                'created_by' => null,
                'updated_by' => null,
                'deleted_by' => null,
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
            ])->all());
        }
    }

    private function seedAcademicSessions(): void
    {
        foreach ([
            ['2023-2024', '2023-08-01', '2024-07-31', false],
            ['2024-2025', '2024-08-01', '2025-07-31', false],
            ['2025-2026', '2025-08-01', '2026-07-31', true],
        ] as $index => [$name, $startDate, $endDate, $isActive]) {
            DB::table('academic_sessions')->insert([
                'id' => $index + 1,
                'uuid' => (string) Str::uuid(),
                'name' => $name,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'is_active' => $isActive,
                'created_by' => null,
                'updated_by' => null,
                'deleted_by' => null,
                'created_at' => now(),
                'updated_at' => now(),
                'deleted_at' => null,
            ]);
        }
    }

    private function districtIdFromLegacyCode(mixed $legacyCode): ?int
    {
        if ($legacyCode === null || $legacyCode === '') {
            return null;
        }

        return DB::table('districts')
            ->where('legacy_code', (string) $legacyCode)
            ->orWhere('code', 'DST-'.$legacyCode)
            ->value('id');
    }

    private function circleIdFromLegacyId(mixed $legacyId): ?int
    {
        if ($legacyId === null || $legacyId === '' || ! Schema::hasTable('circles')) {
            return null;
        }

        return DB::table('circles')->where('legacy_id', (int) $legacyId)->value('id');
    }
}
