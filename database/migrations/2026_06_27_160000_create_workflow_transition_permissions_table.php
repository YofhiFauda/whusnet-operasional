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
        Schema::create('workflow_transition_permissions', function (Blueprint $table) {
            $table->id();
            $table->string('from_status');
            $table->string('to_status');
            $table->string('permission_name');
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('role_workflow_transition', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained('roles')->onDelete('cascade');
            $table->foreignId('workflow_transition_permission_id')
                  ->constrained('workflow_transition_permissions')
                  ->onDelete('cascade')
                  ->name('fk_role_wf_trans_perm_id');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('role_workflow_transition');
        Schema::dropIfExists('workflow_transition_permissions');
    }
};
