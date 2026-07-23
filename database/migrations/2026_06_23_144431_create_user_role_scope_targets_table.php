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
        Schema::create('user_role_scope_targets', function (Blueprint $table) {
            $table->foreignId('user_role_scope_id')->constrained('user_role_scopes')->cascadeOnDelete();
            $table->foreignId('pop_id')->constrained('pops')->cascadeOnDelete();

            $table->primary(['user_role_scope_id', 'pop_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_role_scope_targets');
    }
};
