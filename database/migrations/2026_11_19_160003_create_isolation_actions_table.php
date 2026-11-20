<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Pekerjaan/eksekusi isolir atau pemulihan
     * (docs/plan/Rancangan_Monitoring_Isolir_Pelanggan.md §12.4/§21.2). Batch
     * pertama cuma manual/sinkron (klik = `succeeded` langsung, belum ada
     * integrasi perangkat) — kolom `idempotency_key` & `attempt_number`
     * disiapkan penuh supaya nanti tinggal diisi async tanpa migrasi ulang.
     */
    public function up(): void
    {
        Schema::create('isolation_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('isolation_case_id')->constrained()->cascadeOnDelete();
            $table->string('action_type'); // suspend | restore
            $table->string('status'); // queued | in_progress | succeeded | failed | cancelled
            $table->string('idempotency_key')->unique();
            $table->unsignedInteger('attempt_number')->default(1);
            $table->foreignId('requested_by')->nullable()->constrained('users');
            $table->foreignId('executed_by')->nullable()->constrained('users');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->string('result_code')->nullable();
            $table->text('result_message')->nullable();
            $table->string('device_reference')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['isolation_case_id', 'action_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('isolation_actions');
    }
};
