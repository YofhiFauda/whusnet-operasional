<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fop_tasks', function (Blueprint $table) {
            $table->index('task_date');
        });

        Schema::table('fop_task_user', function (Blueprint $table) {
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('fop_tasks', function (Blueprint $table) {
            $table->dropIndex(['task_date']);
        });

        Schema::table('fop_task_user', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
        });
    }
};
