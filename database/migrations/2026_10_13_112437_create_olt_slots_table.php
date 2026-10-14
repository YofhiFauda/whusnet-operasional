<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('olt_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chassis_node_id')->constrained('network_nodes')->restrictOnDelete();
            $table->unsignedSmallInteger('number');
            $table->foreignId('card_node_id')->nullable()->unique()->constrained('network_nodes')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['chassis_node_id', 'number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('olt_slots');
    }
};
