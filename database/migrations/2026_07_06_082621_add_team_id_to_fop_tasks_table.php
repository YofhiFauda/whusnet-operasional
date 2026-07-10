<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fop_tasks', function (Blueprint $table) {
            $table->foreignId('team_id')->nullable()->after('task_id')
                ->constrained('fop_task_teams')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('fop_tasks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('team_id');
        });
    }
};
