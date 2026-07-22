<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $this->createGeographyMasters();
        $this->addLegacyRelationshipColumns();
        $this->seedFromArchive();
        $this->normalizeExistingRows();
    }

    public function down(): void
    {
        $this->dropApplicationRelationshipColumns();

        foreach (['phads', 'samitis', 'district_unions', 'users', 'districts'] as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            Schema::table($table, function (Blueprint $table): void {
                foreach ([
                    'legacy_id',
                    'legacy_code',
                    'district_id',
                    'district_union_id',
                    'samiti_id',
                    'circle_id',
                    'legacy_district_code',
                    'legacy_district_union_id',
                    'legacy_samiti_id',
                    'legacy_circle_id',
                    'district_union_master_id',
                    'samiti_master_id',
                    'circle_master_id',
                ] as $column) {
                    if (Schema::hasColumn($table->getTable(), $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        Schema::dropIfExists('wards');
        Schema::dropIfExists('cities');
        Schema::dropIfExists('villages');
        Schema::dropIfExists('gram_panchayats');
        Schema::dropIfExists('blocks');
        Schema::dropIfExists('circles');
    }

    private function createGeographyMasters(): void
    {
        if (! Schema::hasTable('circles')) {
            Schema::create('circles', function (Blueprint $table): void {
                $table->id();
                $table->uuid()->unique();
                $table->unsignedInteger('legacy_id')->nullable()->unique();
                $table->string('legacy_code', 40)->nullable()->unique();
                $table->string('name');
                $table->boolean('is_active')->default(true)->index();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('blocks')) {
            Schema::create('blocks', function (Blueprint $table): void {
                $table->id();
                $table->uuid()->unique();
                $table->string('legacy_code', 40)->unique();
                $table->string('name');
                $table->foreignId('district_id')->nullable()->constrained('districts')->nullOnDelete();
                $table->string('legacy_district_code', 40)->nullable()->index();
                $table->boolean('is_active')->default(true)->index();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('gram_panchayats')) {
            Schema::create('gram_panchayats', function (Blueprint $table): void {
                $table->id();
                $table->uuid()->unique();
                $table->string('legacy_code', 40)->unique();
                $table->string('name');
                $table->foreignId('block_id')->nullable()->constrained('blocks')->nullOnDelete();
                $table->string('legacy_block_code', 40)->nullable()->index();
                $table->boolean('is_active')->default(true)->index();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('villages')) {
            Schema::create('villages', function (Blueprint $table): void {
                $table->id();
                $table->uuid()->unique();
                $table->string('legacy_code', 40)->unique();
                $table->string('name');
                $table->foreignId('gram_panchayat_id')->nullable()->constrained('gram_panchayats')->nullOnDelete();
                $table->string('legacy_gram_panchayat_code', 40)->nullable()->index();
                $table->boolean('is_active')->default(true)->index();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('cities')) {
            Schema::create('cities', function (Blueprint $table): void {
                $table->id();
                $table->uuid()->unique();
                $table->string('legacy_code', 40)->unique();
                $table->string('name');
                $table->foreignId('block_id')->nullable()->constrained('blocks')->nullOnDelete();
                $table->string('legacy_block_code', 40)->nullable()->index();
                $table->boolean('is_active')->default(true)->index();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('wards')) {
            Schema::create('wards', function (Blueprint $table): void {
                $table->id();
                $table->uuid()->unique();
                $table->string('legacy_code', 40)->unique();
                $table->string('name');
                $table->foreignId('city_id')->nullable()->constrained('cities')->nullOnDelete();
                $table->string('legacy_city_code', 40)->nullable()->index();
                $table->boolean('is_active')->default(true)->index();
                $table->timestamps();
            });
        }
    }

    private function addLegacyRelationshipColumns(): void
    {
        $this->addColumnIfMissing('districts', 'legacy_code', fn (Blueprint $table) => $table->string('legacy_code', 40)->nullable()->index()->after('code'));

        $this->addColumnIfMissing('district_unions', 'legacy_id', fn (Blueprint $table) => $table->unsignedInteger('legacy_id')->nullable()->unique()->after('id'));
        $this->addColumnIfMissing('district_unions', 'district_id', fn (Blueprint $table) => $table->foreignId('district_id')->nullable()->after('name')->constrained('districts')->nullOnDelete());
        $this->addColumnIfMissing('district_unions', 'circle_id', fn (Blueprint $table) => $table->foreignId('circle_id')->nullable()->after('district_id')->constrained('circles')->nullOnDelete());
        $this->addColumnIfMissing('district_unions', 'legacy_district_code', fn (Blueprint $table) => $table->string('legacy_district_code', 40)->nullable()->index()->after('circle_id'));
        $this->addColumnIfMissing('district_unions', 'legacy_circle_id', fn (Blueprint $table) => $table->unsignedInteger('legacy_circle_id')->nullable()->index()->after('legacy_district_code'));

        $this->addColumnIfMissing('samitis', 'legacy_id', fn (Blueprint $table) => $table->unsignedInteger('legacy_id')->nullable()->unique()->after('id'));
        $this->addColumnIfMissing('samitis', 'district_id', fn (Blueprint $table) => $table->foreignId('district_id')->nullable()->after('name')->constrained('districts')->nullOnDelete());
        $this->addColumnIfMissing('samitis', 'district_union_id', fn (Blueprint $table) => $table->foreignId('district_union_id')->nullable()->after('district_id')->constrained('district_unions')->nullOnDelete());
        $this->addColumnIfMissing('samitis', 'legacy_district_code', fn (Blueprint $table) => $table->string('legacy_district_code', 40)->nullable()->index()->after('district_union_id'));
        $this->addColumnIfMissing('samitis', 'legacy_district_union_id', fn (Blueprint $table) => $table->unsignedInteger('legacy_district_union_id')->nullable()->index()->after('legacy_district_code'));

        $this->addColumnIfMissing('phads', 'legacy_id', fn (Blueprint $table) => $table->unsignedInteger('legacy_id')->nullable()->unique()->after('id'));
        $this->addColumnIfMissing('phads', 'legacy_code', fn (Blueprint $table) => $table->string('legacy_code', 40)->nullable()->index()->after('code'));
        $this->addColumnIfMissing('phads', 'district_id', fn (Blueprint $table) => $table->foreignId('district_id')->nullable()->after('name')->constrained('districts')->nullOnDelete());
        $this->addColumnIfMissing('phads', 'district_union_id', fn (Blueprint $table) => $table->foreignId('district_union_id')->nullable()->after('district_id')->constrained('district_unions')->nullOnDelete());
        $this->addColumnIfMissing('phads', 'samiti_id', fn (Blueprint $table) => $table->foreignId('samiti_id')->nullable()->after('district_union_id')->constrained('samitis')->nullOnDelete());
        $this->addColumnIfMissing('phads', 'legacy_district_code', fn (Blueprint $table) => $table->string('legacy_district_code', 40)->nullable()->index()->after('samiti_id'));
        $this->addColumnIfMissing('phads', 'legacy_district_union_id', fn (Blueprint $table) => $table->unsignedInteger('legacy_district_union_id')->nullable()->index()->after('legacy_district_code'));
        $this->addColumnIfMissing('phads', 'legacy_samiti_id', fn (Blueprint $table) => $table->unsignedInteger('legacy_samiti_id')->nullable()->index()->after('legacy_district_union_id'));

        $this->addColumnIfMissing('users', 'district_union_master_id', fn (Blueprint $table) => $table->foreignId('district_union_master_id')->nullable()->after('districtunion')->constrained('district_unions')->nullOnDelete());
        $this->addColumnIfMissing('users', 'samiti_master_id', fn (Blueprint $table) => $table->foreignId('samiti_master_id')->nullable()->after('samiti')->constrained('samitis')->nullOnDelete());
        $this->addColumnIfMissing('users', 'circle_master_id', fn (Blueprint $table) => $table->foreignId('circle_master_id')->nullable()->after('circle')->constrained('circles')->nullOnDelete());

        foreach ([
            'block_id' => 'blocks',
            'gram_panchayat_id' => 'gram_panchayats',
            'village_id' => 'villages',
            'city_id' => 'cities',
            'ward_id' => 'wards',
        ] as $column => $tableName) {
            $this->addColumnIfMissing('scholarship_applications', $column, fn (Blueprint $table) => $table->foreignId($column)->nullable()->after('phad_id')->constrained($tableName)->nullOnDelete());
        }
    }

    private function seedFromArchive(): void
    {
        if (! Schema::hasTable('source_data_archives')) {
            return;
        }

        $this->seedCircles();
        $this->seedBlocks();
        $this->seedGramPanchayats();
        $this->seedVillages();
        $this->seedCities();
        $this->seedWards();
    }

    private function normalizeExistingRows(): void
    {
        if (Schema::hasTable('districts') && Schema::hasColumn('districts', 'legacy_code')) {
            DB::table('districts')->whereNull('legacy_code')->orderBy('id')->chunkById(500, function ($rows): void {
                foreach ($rows as $row) {
                    $legacyCode = Str::startsWith((string) $row->code, 'DST-') ? Str::after((string) $row->code, 'DST-') : $row->code;
                    DB::table('districts')->where('id', $row->id)->update(['legacy_code' => $legacyCode]);
                }
            });
        }

        $this->normalizeDistrictUnions();
        $this->normalizeSamitis();
        $this->normalizePhads();
        $this->normalizeUsers();
        $this->normalizeApplications();
    }

    private function seedCircles(): void
    {
        foreach ($this->archiveRows('circles') as $payload) {
            DB::table('circles')->updateOrInsert(
                ['legacy_id' => (int) $payload['id']],
                [
                    'uuid' => (string) Str::uuid(),
                    'legacy_code' => (string) $payload['id'],
                    'name' => (string) ($payload['circle_name'] ?? 'Circle '.$payload['id']),
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
        }
    }

    private function seedBlocks(): void
    {
        $districts = DB::table('districts')->pluck('id', 'legacy_code');
        $rows = collect($this->archiveRows('blocks'))->map(fn (array $payload): array => [
            'uuid' => (string) Str::uuid(),
            'legacy_code' => (string) $payload['block_code'],
            'name' => (string) ($payload['block_name'] ?? $payload['block_code']),
            'district_id' => $districts[(string) ($payload['district_code'] ?? '')] ?? null,
            'legacy_district_code' => $payload['district_code'] ?? null,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $rows->chunk(1000)->each(fn ($chunk) => DB::table('blocks')->upsert($chunk->all(), ['legacy_code'], ['name', 'district_id', 'legacy_district_code', 'is_active', 'updated_at']));
    }

    private function seedGramPanchayats(): void
    {
        $blocks = DB::table('blocks')->pluck('id', 'legacy_code');
        $rows = collect($this->archiveRows('gram_panchayat'))->map(fn (array $payload): array => [
            'uuid' => (string) Str::uuid(),
            'legacy_code' => (string) $payload['gp_code'],
            'name' => (string) ($payload['gp_name'] ?? $payload['gp_code']),
            'block_id' => $blocks[(string) ($payload['block_code'] ?? '')] ?? null,
            'legacy_block_code' => $payload['block_code'] ?? null,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $rows->chunk(1000)->each(fn ($chunk) => DB::table('gram_panchayats')->upsert($chunk->all(), ['legacy_code'], ['name', 'block_id', 'legacy_block_code', 'is_active', 'updated_at']));
    }

    private function seedVillages(): void
    {
        $gramPanchayats = DB::table('gram_panchayats')->pluck('id', 'legacy_code');
        $rows = collect($this->archiveRows('villages'))->map(fn (array $payload): array => [
            'uuid' => (string) Str::uuid(),
            'legacy_code' => (string) $payload['village_code'],
            'name' => (string) ($payload['village_name'] ?? $payload['village_code']),
            'gram_panchayat_id' => $gramPanchayats[(string) ($payload['gp_code'] ?? '')] ?? null,
            'legacy_gram_panchayat_code' => $payload['gp_code'] ?? null,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $rows->chunk(1000)->each(fn ($chunk) => DB::table('villages')->upsert($chunk->all(), ['legacy_code'], ['name', 'gram_panchayat_id', 'legacy_gram_panchayat_code', 'is_active', 'updated_at']));
    }

    private function seedCities(): void
    {
        $blocks = DB::table('blocks')->pluck('id', 'legacy_code');
        $rows = collect($this->archiveRows('cities'))->map(fn (array $payload): array => [
            'uuid' => (string) Str::uuid(),
            'legacy_code' => (string) $payload['city_code'],
            'name' => (string) ($payload['city_name'] ?? $payload['city_code']),
            'block_id' => $blocks[(string) ($payload['block_code'] ?? '')] ?? null,
            'legacy_block_code' => $payload['block_code'] ?? null,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $rows->chunk(1000)->each(fn ($chunk) => DB::table('cities')->upsert($chunk->all(), ['legacy_code'], ['name', 'block_id', 'legacy_block_code', 'is_active', 'updated_at']));
    }

    private function seedWards(): void
    {
        $cities = DB::table('cities')->pluck('id', 'legacy_code');
        $rows = collect($this->archiveRows('wards'))->map(fn (array $payload): array => [
            'uuid' => (string) Str::uuid(),
            'legacy_code' => (string) $payload['ward_code'],
            'name' => (string) ($payload['ward_name'] ?? $payload['ward_code']),
            'city_id' => $cities[(string) ($payload['city_code'] ?? '')] ?? null,
            'legacy_city_code' => $payload['city_code'] ?? null,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $rows->chunk(1000)->each(fn ($chunk) => DB::table('wards')->upsert($chunk->all(), ['legacy_code'], ['name', 'city_id', 'legacy_city_code', 'is_active', 'updated_at']));
    }

    private function normalizeDistrictUnions(): void
    {
        foreach ($this->archiveRows('district_union') as $payload) {
            DB::table('district_unions')->where('id', (int) $payload['id'])->update([
                'legacy_id' => (int) $payload['id'],
                'district_id' => $this->districtIdByLegacyCode($payload['district_code'] ?? null),
                'circle_id' => $this->idByColumn('circles', 'legacy_id', $payload['circle_id'] ?? null),
                'legacy_district_code' => $payload['district_code'] ?? null,
                'legacy_circle_id' => $payload['circle_id'] ?? null,
            ]);
        }
    }

    private function normalizeSamitis(): void
    {
        foreach ($this->archiveRows('samiti') as $payload) {
            DB::table('samitis')->where('id', (int) $payload['id'])->update([
                'legacy_id' => (int) $payload['id'],
                'district_id' => $this->districtIdByLegacyCode($payload['district_code'] ?? null),
                'district_union_id' => $this->idByColumn('district_unions', 'legacy_id', $payload['district_union_id'] ?? null),
                'legacy_district_code' => $payload['district_code'] ?? null,
                'legacy_district_union_id' => $payload['district_union_id'] ?? null,
            ]);
        }
    }

    private function normalizePhads(): void
    {
        foreach ($this->archiveRows('phads') as $payload) {
            DB::table('phads')->where('id', (int) $payload['id'])->update([
                'legacy_id' => (int) $payload['id'],
                'legacy_code' => $payload['phad_code'] ?? null,
                'district_id' => $this->districtIdByLegacyCode($payload['district_code'] ?? null),
                'district_union_id' => $this->idByColumn('district_unions', 'legacy_id', $payload['district_union_id'] ?? null),
                'samiti_id' => $this->idByColumn('samitis', 'legacy_id', $payload['samiti_id'] ?? null),
                'legacy_district_code' => $payload['district_code'] ?? null,
                'legacy_district_union_id' => $payload['district_union_id'] ?? null,
                'legacy_samiti_id' => $payload['samiti_id'] ?? null,
            ]);
        }
    }

    private function normalizeUsers(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        if (DB::connection()->getDriverName() !== 'mysql') {
            DB::table('users')->orderBy('id')->chunkById(500, function ($users): void {
                foreach ($users as $user) {
                    DB::table('users')->where('id', $user->id)->update([
                        'district_union_master_id' => $this->idByColumn('district_unions', 'legacy_id', $user->districtunion ?? null),
                        'samiti_master_id' => $this->idByColumn('samitis', 'legacy_id', $user->samiti ?? null),
                        'circle_master_id' => $this->idByColumn('circles', 'legacy_id', $user->circle ?? null),
                    ]);
                }
            });

            return;
        }

        DB::statement('UPDATE users u LEFT JOIN district_unions du ON du.legacy_id = u.districtunion SET u.district_union_master_id = du.id');
        DB::statement('UPDATE users u LEFT JOIN samitis s ON s.legacy_id = u.samiti SET u.samiti_master_id = s.id');
        DB::statement('UPDATE users u LEFT JOIN circles c ON c.legacy_id = u.circle SET u.circle_master_id = c.id');
    }

    private function normalizeApplications(): void
    {
        if (! Schema::hasTable('scholarship_applications')) {
            return;
        }

        if (DB::connection()->getDriverName() !== 'mysql') {
            DB::table('scholarship_applications')->orderBy('id')->chunkById(500, function ($applications): void {
                foreach ($applications as $application) {
                    DB::table('scholarship_applications')->where('id', $application->id)->update([
                        'block_id' => $this->idByColumn('blocks', 'legacy_code', $application->block_code ?? null),
                        'gram_panchayat_id' => $this->idByColumn('gram_panchayats', 'legacy_code', $application->gram_panchayat_code ?? null),
                        'village_id' => $this->idByColumn('villages', 'legacy_code', $application->village_code ?? null),
                        'city_id' => $this->idByColumn('cities', 'legacy_code', $application->city_code ?? null),
                        'ward_id' => $this->idByColumn('wards', 'legacy_code', $application->ward_code ?? null),
                    ]);
                }
            });

            return;
        }

        DB::statement('UPDATE scholarship_applications a LEFT JOIN blocks b ON b.legacy_code = a.block_code SET a.block_id = b.id');
        DB::statement('UPDATE scholarship_applications a LEFT JOIN gram_panchayats gp ON gp.legacy_code = a.gram_panchayat_code SET a.gram_panchayat_id = gp.id');
        DB::statement('UPDATE scholarship_applications a LEFT JOIN villages v ON v.legacy_code = a.village_code SET a.village_id = v.id');
        DB::statement('UPDATE scholarship_applications a LEFT JOIN cities c ON c.legacy_code = a.city_code SET a.city_id = c.id');
        DB::statement('UPDATE scholarship_applications a LEFT JOIN wards w ON w.legacy_code = a.ward_code SET a.ward_id = w.id');
    }

    private function dropApplicationRelationshipColumns(): void
    {
        if (! Schema::hasTable('scholarship_applications')) {
            return;
        }

        Schema::table('scholarship_applications', function (Blueprint $table): void {
            foreach (['block_id', 'gram_panchayat_id', 'village_id', 'city_id', 'ward_id'] as $column) {
                if (Schema::hasColumn('scholarship_applications', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    private function addColumnIfMissing(string $tableName, string $column, callable $definition): void
    {
        if (! Schema::hasTable($tableName) || Schema::hasColumn($tableName, $column)) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($definition): void {
            $definition($table);
        });
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function archiveRows(string $table): array
    {
        if (! Schema::hasTable('source_data_archives')) {
            return [];
        }

        return DB::table('source_data_archives')
            ->where('source_table', $table)
            ->orderByRaw('CAST(source_primary_key AS UNSIGNED)')
            ->get()
            ->map(fn (object $row): array => (array) json_decode((string) $row->payload, true))
            ->all();
    }

    private function districtIdByLegacyCode(mixed $legacyCode): ?int
    {
        if ($legacyCode === null || $legacyCode === '') {
            return null;
        }

        return DB::table('districts')
            ->where('legacy_code', (string) $legacyCode)
            ->orWhere('code', 'DST-'.$legacyCode)
            ->value('id');
    }

    private function idByColumn(string $table, string $column, mixed $value): ?int
    {
        if ($value === null || $value === '' || ! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return null;
        }

        $id = DB::table($table)->where($column, $value)->value('id');

        return $id === null ? null : (int) $id;
    }
};
