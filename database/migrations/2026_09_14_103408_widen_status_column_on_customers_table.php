<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `customers.status` cuma `varchar(30)` — cukup buat semua
     * `WorkflowTransition` lama (terpanjang `installation_in_progress`,
     * 25 karakter), tapi enum baru
     * `waiting_business_development_verification` (ADHOC-67 susulan) 42
     * karakter, KEPOTONG di MySQL (`1406 Data too long for column
     * 'status'`) — ketauan user pas nyoba `finalVerify()` beneran di
     * kategori Bisnis (CID C00RQ002023). SQLite (dipakai test) longgar
     * soal panjang varchar jadi lolos sama sekali di test suite — bug ini
     * cuma nongol di MySQL asli.
     *
     * Dilebarkan ke 60 (headroom di atas 42, bukan pas-pasan) — bukan
     * ngependekin value enum, biar kode/DB tetap deskriptif tanpa
     * disingkat maksa.
     */
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('status', 60)->default('registered')->change();
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('status', 30)->default('registered')->change();
        });
    }
};
