<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Semua alat kerja sekarang opsional — tidak perlu penanda per baris.
        Schema::table('task_work_tools', function (Blueprint $table) {
            $table->dropColumn('is_optional');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('task_work_tools', function (Blueprint $table) {
            $table->boolean('is_optional')->default(false)->after('note');
        });
    }
};
