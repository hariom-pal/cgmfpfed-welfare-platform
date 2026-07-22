<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scholarship_applications', function (Blueprint $table): void {
            if (! Schema::hasColumn('scholarship_applications', 'scholarship_session_id')) {
                $table->foreignId('scholarship_session_id')
                    ->nullable()
                    ->after('academic_session_id')
                    ->constrained('academic_sessions')
                    ->nullOnDelete();
            }
        });

        DB::table('scholarship_applications')
            ->select(['id', 'scholarship_session', 'created_at'])
            ->whereNull('scholarship_session_id')
            ->orderBy('id')
            ->chunkById(500, function ($applications): void {
                foreach ($applications as $application) {
                    $session = $this->sessionForApplication($application);
                    if ($session === null) {
                        continue;
                    }

                    DB::table('scholarship_applications')
                        ->where('id', $application->id)
                        ->update([
                            'scholarship_session_id' => $session->id,
                            'scholarship_session' => $session->name,
                        ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('scholarship_applications', function (Blueprint $table): void {
            if (Schema::hasColumn('scholarship_applications', 'scholarship_session_id')) {
                $table->dropConstrainedForeignId('scholarship_session_id');
            }
        });
    }

    private function sessionForApplication(object $application): ?object
    {
        $createdAt = $application->created_at !== null ? Carbon::parse($application->created_at) : null;

        if ($createdAt !== null) {
            $session = DB::table('academic_sessions')
                ->whereDate('start_date', '<=', $createdAt->toDateString())
                ->whereDate('end_date', '>=', $createdAt->toDateString())
                ->orderByDesc('start_date')
                ->first();

            if ($session !== null) {
                return $session;
            }
        }

        if ($application->scholarship_session !== null && $application->scholarship_session !== '') {
            $session = DB::table('academic_sessions')
                ->where('name', (string) $application->scholarship_session)
                ->first();

            if ($session !== null) {
                return $session;
            }
        }

        $session = DB::table('academic_sessions')
            ->where('is_active', true)
            ->orderByDesc('start_date')
            ->first();

        if ($session !== null) {
            return $session;
        }

        return DB::table('academic_sessions')
            ->orderByDesc('start_date')
            ->first();
    }
};
