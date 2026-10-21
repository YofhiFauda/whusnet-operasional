<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bukti tertulis rekonsiliasi cutover (§16.4 #6): per periode & Cabang,
     * empat metrik data lama vs proyeksi baru. `pop_id` null = data lama yang
     * tidak bisa ditautkan ke Cabang mana pun (selalu dihitung selisih).
     * Append-only; satu eksekusi = satu `run_key`.
     */
    public function up(): void
    {
        Schema::create('infrastructure_reconciliation_runs', function (Blueprint $table) {
            $table->id();
            $table->uuid('run_key');
            $table->string('period', 7);
            $table->foreignId('pop_id')->nullable()->constrained('pops')->restrictOnDelete();
            $table->json('legacy_metrics');
            $table->json('projection_metrics');
            $table->json('diff');
            $table->boolean('is_zero');
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['period', 'run_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('infrastructure_reconciliation_runs');
    }
};
