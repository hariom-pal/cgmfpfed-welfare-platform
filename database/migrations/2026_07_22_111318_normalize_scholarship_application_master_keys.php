<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('scholarship_applications')) {
            return;
        }

        DB::table('scholarship_applications')
            ->select('id', 'district_id', 'phad_id')
            ->orderBy('id')
            ->chunkById(500, function ($applications): void {
                foreach ($applications as $application) {
                    $updates = [];

                    if ($application->district_id !== null) {
                        $districtId = DB::table('districts')
                            ->where('code', 'DST-'.$application->district_id)
                            ->value('id');

                        if ($districtId !== null) {
                            $updates['district_id'] = (int) $districtId;
                        }
                    }

                    if ($application->phad_id !== null) {
                        $phadId = DB::table('phads')
                            ->where('code', 'like', 'PHD-'.$application->phad_id.'-%')
                            ->value('id');

                        if ($phadId !== null) {
                            $updates['phad_id'] = (int) $phadId;
                        }
                    }

                    if ($updates !== []) {
                        DB::table('scholarship_applications')
                            ->where('id', $application->id)
                            ->update($updates);
                    }
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Data normalization is intentionally not reversed.
    }
};
