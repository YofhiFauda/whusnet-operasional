<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Alat kerja yang dibutuhkan satu FopTask.
     *
     * Anchor ke `fop_task_id`, BUKAN ke `customer_surveys`. Alasannya sama
     * dengan `task_materials`: FopTask satu-satunya entitas yang dimiliki SEMUA
     * jenis pekerjaan (SURVEY, PSB, MTN, C-REQ, O-REQ, INFR REQ). Rancangan
     * awal yang menempelkannya ke survey ditolak karena MTN/C-REQ tidak pernah
     * lewat survey — padahal perbaikan gangguan justru yang paling sering butuh
     * tangga & splicer, dan mereka tidak akan pernah bisa punya daftar alat.
     *
     * TIDAK ada kolom `kind` (estimasi/terpakai) seperti `task_materials`.
     * Untuk material, selisih estimasi-vs-realisasi itu uang — nilai bisnisnya
     * jelas. Untuk alat kerja, memaksa teknisi mencentang ulang "benar, saya
     * membawa tangga" cuma menambah isian di lapangan tanpa menghasilkan angka
     * yang dipakai siapa pun. Satu daftar per task, boleh disunting.
     */
    public function up(): void
    {
        Schema::create('task_work_tools', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fop_task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();

            // FK boleh null kalau masternya dihapus; `tool_name` snapshot yang
            // menjaga baris lama tetap terbaca. Pola yang sama dengan
            // task_materials.item_name — riwayat tidak boleh bergantung pada
            // baris master yang masih hidup.
            $table->foreignId('work_tool_id')->nullable()->constrained()->nullOnDelete();
            $table->string('tool_name', 100);
            $table->string('note')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['fop_task_id']);
            $table->index(['customer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_work_tools');
    }
};
