<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fop_task_teams', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pop_id');
        });
    }

    public function down(): void
    {
        Schema::table('fop_task_teams', function (Blueprint $table) {
            $table->foreignId('pop_id')->nullable()->constrained('pops')->onDelete('set null');
        });
    }
};
