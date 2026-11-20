<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Timeline/audit trail kasus isolir — APPEND-ONLY
     * (docs/plan/Rancangan_Monitoring_Isolir_Pelanggan.md §12.3/§4.4). Baris
     * di sini TIDAK PERNAH diubah/dihapus lewat kode aplikasi (model
     * `IsolationEvent` sengaja tidak dipakai lewat `update()`/`delete()`).
     */
    public function up(): void
    {
        Schema::create('isolation_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('isolation_case_id')->constrained()->cascadeOnDelete();
            $table->string('event_type');
            $table->string('from_status')->nullable();
            $table->string('to_status')->nullable();
            $table->string('actor_type'); // 'user' | 'system'
            $table->foreignId('actor_id')->nullable()->constrained('users');
            $table->text('description')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamp('created_at');

            $table->index(['isolation_case_id', 'occurred_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('isolation_events');
    }
};
