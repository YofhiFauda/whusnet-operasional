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
        Schema::create('import_batches', function (Blueprint $blueprint) {
            $blueprint->id();
            $blueprint->string('batch_number')->unique();
            $blueprint->string('file_name')->nullable();
            $blueprint->foreignId('uploaded_by')->constrained('users');
            $blueprint->integer('total_rows')->default(0);
            $blueprint->integer('valid_rows')->default(0);
            $blueprint->integer('invalid_rows')->default(0);
            $blueprint->integer('imported_rows')->default(0);
            $blueprint->string('status')->default('pending'); // pending, previewed, imported, failed
            $blueprint->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('import_batches');
    }
};
