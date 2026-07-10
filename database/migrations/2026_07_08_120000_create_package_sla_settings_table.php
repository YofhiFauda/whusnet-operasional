<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('package_sla_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('internet_package_id')->constrained('internet_packages')->cascadeOnDelete();
            $table->string('task_type', 20);
            $table->unsignedInteger('sla_duration');
            $table->enum('sla_unit', ['hour', 'day'])->default('day');
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['internet_package_id', 'task_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('package_sla_settings');
    }
};
