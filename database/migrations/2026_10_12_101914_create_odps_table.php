<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('odps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pop_id')->constrained()->restrictOnDelete();
            $table->string('code', 50)->unique();
            $table->string('name', 150);
            $table->timestamps();
        });

        Schema::create('odp_ports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('odp_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('number');
            $table->boolean('is_used')->default(false);
            $table->timestamps();
            $table->unique(['odp_id', 'number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('odp_ports');
        Schema::dropIfExists('odps');
    }
};
