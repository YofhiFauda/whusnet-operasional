<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `tasks.completed_by` — teknisi yang menekan "Selesai" & mengirim laporan.
 * Beda dari `updated_by`: kolom itu generic, ke-overwrite tiap update apapun
 * (start/pending/cancel/reassign), jadi begitu ada update lagi setelah
 * selesai, jejak "siapa yang menyelesaikan" hilang. `completed_by` diisi
 * SEKALI di `TaskService::complete()` dan tidak pernah ditimpa lagi —
 * dipakai bareng `completed_at` yang juga immutable begitu diisi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->foreignId('completed_by')->nullable()->after('completed_at')
                ->constrained('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('completed_by');
        });
    }
};
