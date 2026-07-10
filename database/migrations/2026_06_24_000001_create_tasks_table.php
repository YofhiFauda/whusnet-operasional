<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->string('task_number')->unique()->comment('Nomor task unik, e.g. TASK-2026-001');
            $table->foreignId('customer_id')->nullable()->constrained('customers')->onDelete('restrict');
            $table->foreignId('pop_id')->constrained('pops')->onDelete('restrict');
            $table->string('task_type', 30)->comment('survey|pemasangan|maintenance|ambil_modem|relokasi');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status', 30)->default('draft')->comment('draft|terjadwal|in_progress|selesai|dibatalkan|pending');
            $table->timestamp('scheduled_at')->nullable()->comment('Waktu jadwal task');
            $table->timestamp('started_at')->nullable()->comment('Waktu mulai actual');
            $table->timestamp('completed_at')->nullable()->comment('Waktu selesai actual');
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason')->nullable();
            $table->string('pending_reason')->nullable();
            $table->foreignId('fop_id')->nullable()->constrained('users')->onDelete('set null')->comment('FOP yang membuat/mengelola task');
            $table->unsignedSmallInteger('sla_minutes')->nullable()->comment('SLA dalam menit: survey=120, instalasi=240, maintenance=180');
            $table->boolean('conflict_override')->default(false)->comment('Apakah konflik jadwal di-override');
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();

            $table->index('pop_id');
            $table->index('customer_id');
            $table->index('status');
            $table->index('task_type');
            $table->index('scheduled_at');
            $table->index('fop_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
