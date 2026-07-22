<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('scholarship_applications') || ! Schema::hasTable('academic_sessions')) {
            return;
        }

        DB::table('scholarship_applications')
            ->select(['id', 'created_at'])
            ->orderBy('id')
            ->chunkById(500, function ($applications): void {
                foreach ($applications as $application) {
                    $sessionId = $this->sessionIdForDate($application->created_at);

                    if ($sessionId === null) {
                        continue;
                    }

                    DB::table('scholarship_applications')
                        ->where('id', $application->id)
                        ->update(['academic_session_id' => $sessionId]);
                }
            });
    }

    public function down(): void
    {
        // One-time data correction is intentionally not reversed.
    }

    private function sessionIdForDate(mixed $date): ?int
    {
        $applicationDate = Carbon::parse($date ?: now())->toDateString();

        $sessionId = DB::table('academic_sessions')
            ->whereDate('start_date', '<=', $applicationDate)
            ->whereDate('end_date', '>=', $applicationDate)
            ->orderByDesc('start_date')
            ->value('id');

        if ($sessionId !== null) {
            return (int) $sessionId;
        }

        $sessionId = DB::table('academic_sessions')
            ->whereDate('start_date', '<=', $applicationDate)
            ->orderByDesc('start_date')
            ->value('id');

        if ($sessionId !== null) {
            return (int) $sessionId;
        }

        $sessionId = DB::table('academic_sessions')
            ->where('is_active', true)
            ->orderByDesc('start_date')
            ->value('id');

        if ($sessionId !== null) {
            return (int) $sessionId;
        }

        $sessionId = DB::table('academic_sessions')
            ->orderByDesc('start_date')
            ->value('id');

        return $sessionId !== null ? (int) $sessionId : null;
    }
};
