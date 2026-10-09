<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Penunjukan PIC Gudang per cabang — TERPISAH dari scope POP user
     * (`user_role_scopes`). Scope menjawab "data mana yang boleh dia lihat",
     * tabel ini menjawab "gudang cabang mana dia jadi penanggung jawab".
     * Pivot (bukan kolom tunggal di `pops`) supaya mendukung 1 cabang banyak
     * PIC maupun 1 PIC banyak cabang kecil.
     *
     * Lihat docs/plan/warehouse/rancangan-teknisi-pic-gudang-cabang.md §5.3.2.
     */
    public function up(): void
    {
        Schema::create('warehouse_pop_pics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pop_id')->constrained('pops')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['pop_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouse_pop_pics');
    }
};
