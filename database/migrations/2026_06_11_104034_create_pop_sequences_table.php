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
        Schema::create('pop_sequences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pop_id')->constrained('pops')->cascadeOnDelete();
            $table->string('sequence_type', 30);
            $table->unsignedBigInteger('current_number')->default(0);
            $table->timestamps();

            $table->unique(['pop_id', 'sequence_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pop_sequences');
    }
};
