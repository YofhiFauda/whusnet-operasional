<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * `TaskStatus::RESCHEDULE` dihapus sebagai enum case terpisah (lihat
     * docs/project_status_label_unifikasi.md § DESAIN FINAL) — dileburin jadi
     * `pending` biasa (perilaku lepas-tim + rebuild jadwal yang dulu cuma
     * dipunyai `reschedule` sekarang jadi satu-satunya perilaku "pending").
     */
    public function up(): void
    {
        DB::table('tasks')->where('status', 'reschedule')->update(['status' => 'pending']);
    }

    public function down(): void
    {
        // Data lama (mana row yang tadinya 'reschedule') gak bisa direkonstruksi
        // dari 'pending' polos — down() sengaja no-op, `reschedule` enum case-nya
        // sendiri udah dihapus dari kode jadi gak ada yang baca nilai itu lagi.
    }
};
