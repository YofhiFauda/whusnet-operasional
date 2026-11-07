<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Semua FOP (role 'fop') ALL_POP — approve/reject Pengajuan Barang TIDAK
 * bisa dibatasi via Scope POP (itu buat cabang, bukan buat "siapa di antara
 * role yang sama"). Keputusan user 2026-11-07: cuma 1 FOP tertentu yang
 * berwenang approve, 3 FOP lain cuma create+view. Flag boolean per-user ini
 * SATU-SATUNYA gate tambahan (dicek di `PengajuanBarangService::approve()`/
 * `reject()`, bukan cuma UI) di atas permission
 * `warehouse_pengajuan_barang.approve` yang tetap dipegang semua FOP lewat
 * Role Matrix — bukan permission baru per-user (CLAUDE.md RBAC: dilarang
 * kasih permission langsung ke user tanpa matrix role), ini atribut
 * operasional "siapa PIC approval", diedit lewat halaman Kelola User.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_pengajuan_barang_approver')->default(false)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_pengajuan_barang_approver');
        });
    }
};
