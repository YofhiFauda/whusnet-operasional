<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Unifikasi status FopTask ke vocab TaskStatus (draft/terjadwal/in_progress/
 * selesai/dibatalkan/pending) — FopTaskStatus enum (Proses/Pending/Selesai/
 * Cancel) dihapus. Data lama di-remap langsung (dev, gak perlu presisi
 * historis) — lihat docs/project_status_label_unifikasi.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('fop_tasks')->where('status', 'Proses')->update(['status' => 'in_progress']);
        DB::table('fop_tasks')->where('status', 'Pending')->update(['status' => 'pending']);
        DB::table('fop_tasks')->where('status', 'Selesai')->update(['status' => 'selesai']);
        DB::table('fop_tasks')->where('status', 'Cancel')->update(['status' => 'dibatalkan']);

        Schema::table('fop_tasks', function (Blueprint $table) {
            $table->string('status', 20)->default('draft')->comment('draft|terjadwal|in_progress|pending|selesai|dibatalkan')->change();
        });
    }

    public function down(): void
    {
        DB::table('fop_tasks')->where('status', 'in_progress')->update(['status' => 'Proses']);
        DB::table('fop_tasks')->where('status', 'terjadwal')->update(['status' => 'Proses']);
        DB::table('fop_tasks')->where('status', 'draft')->update(['status' => 'Proses']);
        DB::table('fop_tasks')->where('status', 'pending')->update(['status' => 'Pending']);
        DB::table('fop_tasks')->where('status', 'selesai')->update(['status' => 'Selesai']);
        DB::table('fop_tasks')->where('status', 'dibatalkan')->update(['status' => 'Cancel']);

        Schema::table('fop_tasks', function (Blueprint $table) {
            $table->string('status', 20)->default('Proses')->comment('Proses|Cancel|Pending')->change();
        });
    }
};
