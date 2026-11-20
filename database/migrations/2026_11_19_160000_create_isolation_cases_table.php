<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Satu record = satu siklus kasus isolir untuk SATU layanan
     * (docs/plan/Rancangan_Monitoring_Isolir_Pelanggan.md §12.1/§21.2).
     * Menggantikan `isolir_queue_entries` (ADHOC-306, belum pernah commit) —
     * bukan ekstensi dari tabel itu.
     */
    public function up(): void
    {
        Schema::create('isolation_cases', function (Blueprint $table) {
            $table->id();
            $table->string('case_number')->unique();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained('customer_services')->cascadeOnDelete();
            // Denormalisasi dari customer->pop_id — HasPopScope butuh kolom
            // `pop_id` langsung di tabel ini, tidak bisa join tiap query.
            $table->foreignId('pop_id')->constrained('pops');
            $table->string('status');
            $table->string('trigger_type');
            $table->string('billing_period')->nullable();
            $table->decimal('outstanding_amount_at_creation', 15, 2)->default(0);
            $table->decimal('current_outstanding_amount', 15, 2)->default(0);
            $table->timestamp('detected_at');
            $table->timestamp('isolation_started_at')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('restoration_started_at')->nullable();
            $table->timestamp('restored_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancellation_reason')->nullable();
            $table->timestamps();

            // BR-03: satu kasus AKTIF per layanan. MySQL tidak punya partial
            // unique index — `active_slot` cuma diisi `1` selama status BUKAN
            // completed/cancelled (lihat IsolationCase::scopeActive() &
            // model boot), NULL selain itu. Unique index mengabaikan banyak
            // NULL di MySQL, jadi ini efektif jadi guard "maks satu baris
            // aktif per service_id" tanpa partial index.
            $table->unsignedTinyInteger('active_slot')->nullable();
            $table->unique(['service_id', 'active_slot'], 'isolation_cases_service_active_unique');

            $table->index(['status']);
            $table->index(['pop_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('isolation_cases');
    }
};
