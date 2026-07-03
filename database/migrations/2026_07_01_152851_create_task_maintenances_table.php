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
        Schema::create('task_maintenances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('tasks')->onDelete('cascade');
            $table->text('kendala_teknis');
            $table->string('kabel')->nullable();
            $table->string('modem')->nullable();
            $table->string('patchcord')->nullable();
            $table->string('sleeve')->nullable();
            $table->string('lainnya')->nullable();
            $table->string('opm_photo')->nullable();
            $table->string('speedtest_photo')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('task_maintenances');
    }
};
