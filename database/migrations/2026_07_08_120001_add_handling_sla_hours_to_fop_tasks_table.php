<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fop_tasks', function (Blueprint $table) {
            // Snapshot Master Timeline SLA (jam) saat tiket dibuat, resolve dari paket
            // internet customer saat itu. Beku — ganti paket customer belakangan tidak
            // mengubah tiket yang sudah dibuat.
            $table->unsignedInteger('handling_sla_hours')->nullable()->after('priority');
        });
    }

    public function down(): void
    {
        Schema::table('fop_tasks', function (Blueprint $table) {
            $table->dropColumn('handling_sla_hours');
        });
    }
};
