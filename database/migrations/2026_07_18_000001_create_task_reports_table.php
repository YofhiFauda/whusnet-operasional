<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->unique()->constrained('tasks')->cascadeOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('pending_at')->nullable();
            $table->timestamp('resumed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedInteger('total_duration_minutes')->default(0);
            $table->unsignedInteger('sla_target_minutes')->nullable();
            $table->string('sla_status', 20)->nullable()->comment('on_time|over');
            $table->unsignedInteger('sla_overrun_minutes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_reports');
    }
};
