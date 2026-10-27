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
        Schema::create('network_node_ports', function (Blueprint $table) {
            $table->id();
            // cascadeOnDelete (bukan restrictOnDelete seperti FK network_nodes):
            // port tidak punya makna mandiri tanpa node-nya, jadi ikut terhapus.
            $table->foreignId('network_node_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('number');
            $table->boolean('is_used')->default(false);
            $table->timestamps();
            $table->unique(['network_node_id', 'number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('network_node_ports');
    }
};
