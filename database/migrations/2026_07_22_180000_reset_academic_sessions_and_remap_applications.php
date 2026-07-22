<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * @var list<array{name: string, start_date: string, end_date: string, is_active: bool}>
     */
    private array $sessions = [
        ['name' => '2023-2024', 'start_date' => '2023-08-01', 'end_date' => '2024-07-31', 'is_active' => false],
        ['name' => '2024-2025', 'start_date' => '2024-08-01', 'end_date' => '2025-07-31', 'is_active' => false],
        ['name' => '2025-2026', 'start_date' => '2025-08-01', 'end_date' => '2026-07-31', 'is_active' => true],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('academic_sessions')) {
            return;
        }

        Schema::disableForeignKeyConstraints();

        try {
            DB::table('academic_sessions')->delete();

            foreach ($this->sessions as $index => $session) {
                DB::table('academic_sessions')->insert([
                    'id' => $index + 1,
                    'uuid' => (string) Str::uuid(),
                    'name' => $session['name'],
                    'start_date' => $session['start_date'],
                    'end_date' => $session['end_date'],
                    'is_active' => $session['is_active'],
                    'created_by' => null,
                    'updated_by' => null,
                    'deleted_by' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                    'deleted_at' => null,
                ]);
            }

            $this->remapApplications();
        } finally {
            Schema::enableForeignKeyConstraints();
        }
    }

    public function down(): void
    {
        // This is a one-time master-data correction and application remap.
    }

    private function remapApplications(): void
    {
        if (! Schema::hasTable('scholarship_applications')) {
            return;
        }

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement(<<<'SQL'
                UPDATE scholarship_applications applications
                JOIN academic_sessions sessions
                  ON DATE(applications.created_at) BETWEEN sessions.start_date AND sessions.end_date
                SET applications.academic_session_id = sessions.id,
                    applications.scholarship_session_id = sessions.id,
                    applications.scholarship_session = sessions.name
            SQL);

            DB::statement(<<<'SQL'
                UPDATE scholarship_applications applications
                JOIN academic_sessions sessions
                  ON sessions.is_active = 1
                SET applications.academic_session_id = sessions.id,
                    applications.scholarship_session_id = sessions.id,
                    applications.scholarship_session = sessions.name
                WHERE applications.academic_session_id IS NULL
                   OR applications.scholarship_session_id IS NULL
            SQL);

            return;
        }

        DB::table('scholarship_applications')
            ->select(['id', 'created_at'])
            ->orderBy('id')
            ->chunkById(500, function ($applications): void {
                foreach ($applications as $application) {
                    $session = $this->sessionForDate($application->created_at);
                    $updates = ['academic_session_id' => $session->id];

                    if (Schema::hasColumn('scholarship_applications', 'scholarship_session_id')) {
                        $updates['scholarship_session_id'] = $session->id;
                    }

                    if (Schema::hasColumn('scholarship_applications', 'scholarship_session')) {
                        $updates['scholarship_session'] = $session->name;
                    }

                    DB::table('scholarship_applications')
                        ->where('id', $application->id)
                        ->update($updates);
                }
            });
    }

    private function sessionForDate(mixed $date): object
    {
        $applicationDate = Carbon::parse($date ?: now())->toDateString();

        $session = DB::table('academic_sessions')
            ->whereDate('start_date', '<=', $applicationDate)
            ->whereDate('end_date', '>=', $applicationDate)
            ->orderByDesc('start_date')
            ->first();

        if ($session !== null) {
            return $session;
        }

        return DB::table('academic_sessions')
            ->where('is_active', true)
            ->firstOrFail();
    }
};
