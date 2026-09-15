<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Index gabungan buat leaderboard performa individu Dashboard NOC
     * (NocDashboardController) — query-nya `WHERE action = ... AND happened_at
     * BETWEEN ... GROUP BY actor_id`. Tanpa index ini full-scan begitu volume
     * ticket_histories membesar (docs/plan/noc-dashboard-analysis.md §2/§3).
     */
    public function up(): void
    {
        Schema::table('ticket_histories', function (Blueprint $table) {
            $table->index(['actor_id', 'action', 'happened_at'], 'ticket_histories_actor_action_happened_idx');
        });
    }

    public function down(): void
    {
        Schema::table('ticket_histories', function (Blueprint $table) {
            $table->dropIndex('ticket_histories_actor_action_happened_idx');
        });
    }
};
