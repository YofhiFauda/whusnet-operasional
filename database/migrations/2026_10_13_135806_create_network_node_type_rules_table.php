<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('network_node_type_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('child_type_id')->constrained('network_node_types')->cascadeOnDelete();
            $table->foreignId('parent_type_id')->nullable()->constrained('network_node_types')->cascadeOnDelete();
            $table->string('anchor_pop_type', 20)->nullable();
            $table->timestamps();
            $table->unique(['child_type_id', 'parent_type_id']);
            $table->unique(['child_type_id', 'anchor_pop_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('network_node_type_rules');
    }
};
