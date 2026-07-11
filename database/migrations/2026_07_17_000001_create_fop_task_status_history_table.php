<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fop_task_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fop_task_id')->constrained('fop_tasks')->cascadeOnDelete();
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30);
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('changed_at');
            $table->timestamps();

            $table->index('fop_task_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fop_task_status_history');
    }
};
