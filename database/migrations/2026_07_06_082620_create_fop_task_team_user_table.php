<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fop_task_team_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fop_task_team_id')->constrained('fop_task_teams')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->timestamps();

            $table->unique(['fop_task_team_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fop_task_team_user');
    }
};
