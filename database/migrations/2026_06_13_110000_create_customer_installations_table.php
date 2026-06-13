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
        Schema::create('customer_installations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->onDelete('cascade');
            $table->string('installation_status')->default('scheduled');
            $table->date('scheduled_date')->nullable();
            $table->time('scheduled_time')->nullable();
            $table->foreignId('technician_id')->nullable()->constrained('users')->onDelete('set null');
            $table->date('finished_date')->nullable();
            $table->string('installation_photo')->nullable();
            $table->text('installation_note')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_installations');
    }
};
