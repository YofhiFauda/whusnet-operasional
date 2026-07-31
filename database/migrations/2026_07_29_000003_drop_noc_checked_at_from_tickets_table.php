<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hapus `tickets.noc_checked_at` — window "Pending NOC" + aksi Oncheck NOC
 * dipensiunkan (ADHOC-06, 2026-07-29).
 *
 * Sekarang tiket yang dikirim ke NOC LANGSUNG berstatus diproses; gak ada lagi
 * state antara "sudah dikirim tapi belum diterima", jadi kolom ini gak punya
 * pembaca maupun penulis.
 *
 * DESTRUKTIF & disetujui eksplisit oleh pemilik produk: jam Oncheck yang sudah
 * tersimpan hilang permanen. `down()` cuma mengembalikan KOLOMNYA (kosong) —
 * isinya gak bisa dipulihkan dari sini. Jejak siapa yang dulu meng-Oncheck
 * TETAP ada di `ticket_histories` (action `dicek_noc`), jadi audit lama gak
 * hilang total — itu yang bikin penghapusan kolom ini bisa diterima.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('tickets', 'noc_checked_at')) {
            return;
        }

        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn('noc_checked_at');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('tickets', 'noc_checked_at')) {
            return;
        }

        Schema::table('tickets', function (Blueprint $table) {
            $table->timestamp('noc_checked_at')->nullable()->after('status');
        });
    }
};
