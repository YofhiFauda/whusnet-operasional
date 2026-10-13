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
        Schema::create('network_nodes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('network_nodes')->restrictOnDelete();
            $table->foreignId('anchor_pop_id')->nullable()->constrained('pops')->restrictOnDelete();
            $table->foreignId('node_type_id')->constrained('network_node_types')->restrictOnDelete();
            $table->string('code', 50)->unique();
            $table->string('name', 150);
            $table->integer('capacity')->nullable();
            $table->text('address')->nullable();
            $table->string('village', 100)->nullable();
            $table->string('district', 100)->nullable();
            $table->string('city', 100)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('status', 20)->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['parent_id']);
            $table->index(['anchor_pop_id']);
            $table->index(['node_type_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('network_nodes');
    }
};
