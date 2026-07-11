<?php

use App\Models\FopTask;
use App\Services\FopTaskTeamService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fop_tasks', function (Blueprint $table) {
            $table->timestamp('manual_override_at')->nullable()->after('team_id')
                ->comment('Kalau terisi, hasil drop-in manual (C2/C3) ke Team ini gak boleh ketimpa rebuild otomatis.');
        });

        // Migrasi data existing: rebuild retroaktif per task_date yang punya task aktif,
        // supaya team lama yang dibuat manual sebelum Auto-Team aktif jadi konsisten
        // dengan hasil algoritma connected components.
        $dates = FopTask::whereIn('status', ['Proses', 'Pending'])
            ->selectRaw('DATE(task_date) as d')
            ->distinct()
            ->pluck('d');

        foreach ($dates as $d) {
            if (!$d) {
                continue;
            }

            app(FopTaskTeamService::class)->rebuildTeamsForDate(Carbon::parse($d));
        }
    }

    public function down(): void
    {
        Schema::table('fop_tasks', function (Blueprint $table) {
            $table->dropColumn('manual_override_at');
        });
    }
};
