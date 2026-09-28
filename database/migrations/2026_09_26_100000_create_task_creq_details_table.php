<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('task_creq_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('tasks')->onDelete('cascade');
            $table->string('category');
            $table->string('category_custom_name')->nullable();
            $table->decimal('tikor_lama_lat', 10, 7)->nullable();
            $table->decimal('tikor_lama_lng', 10, 7)->nullable();
            $table->decimal('tikor_baru_lat', 10, 7)->nullable();
            $table->decimal('tikor_baru_lng', 10, 7)->nullable();
            $table->boolean('is_billable')->default(false);
            $table->text('billing_note')->nullable();
            // Cuma bermakna kalau is_billable=true — lihat CReqVerificationStatus.
            $table->string('verification_status')->default('pending');
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('task_creq_details');
    }
};
