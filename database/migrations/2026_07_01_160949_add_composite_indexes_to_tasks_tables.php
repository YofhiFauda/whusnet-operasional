<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            // Conflict detection: tasks overlapping time window for given statuses
            $table->index(['status', 'scheduled_at'], 'tasks_status_scheduled_idx');

            // FOP scope queries: filter by pop + status + date range
            $table->index(['pop_id', 'status', 'scheduled_at'], 'tasks_pop_status_scheduled_idx');

            // teamCanAddTask: count active tasks per pop per day
            $table->index(['pop_id', 'status'], 'tasks_pop_status_idx');

            // Customer workflow lookups (setPending, review)
            $table->index(['customer_id', 'task_type'], 'tasks_customer_type_idx');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropIndex('tasks_status_scheduled_idx');
            $table->dropIndex('tasks_pop_status_scheduled_idx');
            $table->dropIndex('tasks_pop_status_idx');
            $table->dropIndex('tasks_customer_type_idx');
        });
    }
};
