<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tagihan Manual yang diterbitkan dari Verifikasi Biaya C-REQ (keputusan user
 * 2026-09-28: "Setujui & Terbitkan Tagihan" di halaman C-REQ itu sendiri).
 * Dulu Setujui cuma mengubah status lalu melempar CS ke /invoices/create —
 * tagihannya tidak tertaut ke task, dan kalau form itu tidak diselesaikan,
 * biaya C-REQ tercatat "Terverifikasi" tapi tidak pernah ditagih.
 *
 * nullOnDelete: tagihan tidak dihapus di alur normal (FK induknya restrict),
 * tapi jalur pembersihan data dobel (CleanupLegacyDuplicateInvoicesCommand)
 * tidak boleh terhalang oleh tautan ini.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('task_creq_details', function (Blueprint $table) {
            $table->foreignId('invoice_id')->nullable()->after('rejection_reason')->constrained('invoices')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('task_creq_details', function (Blueprint $table) {
            $table->dropConstrainedForeignId('invoice_id');
        });
    }
};
