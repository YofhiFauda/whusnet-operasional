<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $duplicate = DB::table('fop_tasks')
            ->select('task_id')
            ->whereNotNull('task_id')
            ->groupBy('task_id')
            ->havingRaw('COUNT(*) > 1')
            ->first();

        if ($duplicate !== null) {
            throw new RuntimeException(
                "FopTask ganda untuk task_id {$duplicate->task_id}; periksa dan rekonsiliasi data sebelum migrasi."
            );
        }

        Schema::table('fop_tasks', function (Blueprint $table): void {
            $table->unique('task_id', 'fop_tasks_task_id_unique');
        });
    }

    public function down(): void
    {
        Schema::table('fop_tasks', function (Blueprint $table): void {
            $table->dropUnique('fop_tasks_task_id_unique');
        });
    }
};
