<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Task 12 — alasan wajib buat cancel Task FOP (task_type NON-SRV/PSB, yang itu
 * udah dikunci total lewat halaman Customer, lihat migrasi 2026-07-20/21).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fop_tasks', function (Blueprint $table) {
            $table->text('cancel_reason')->nullable()->after('cancelled_at');
        });
    }

    public function down(): void
    {
        Schema::table('fop_tasks', function (Blueprint $table) {
            $table->dropColumn('cancel_reason');
        });
    }
};
