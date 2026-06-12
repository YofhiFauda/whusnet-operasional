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
        Schema::create('import_errors', function (Blueprint $blueprint) {
            $blueprint->id();
            $blueprint->foreignId('import_batch_id')->constrained('import_batches')->cascadeOnDelete();
            $blueprint->integer('row_number')->nullable();
            $blueprint->string('field_name')->nullable();
            $blueprint->text('error_message');
            $blueprint->json('raw_data')->nullable();
            $blueprint->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('import_errors');
    }
};
