<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('infrastructure_reconciliation_runs', function (Blueprint $table): void {
            $table->string('trigger', 16)->default('manual');
            $table->string('kind', 16)->default('snapshot');
            $table->timestamp('observed_at')->nullable();
            $table->unsignedSmallInteger('evidence_version')->nullable();
            $table->index(['observed_at', 'trigger', 'kind'], 'infra_reconciliation_observations');
        });
    }

    public function down(): void
    {
        Schema::table('infrastructure_reconciliation_runs', function (Blueprint $table): void {
            $table->dropIndex('infra_reconciliation_observations');
            $table->dropColumn(['trigger', 'kind', 'observed_at', 'evidence_version']);
        });
    }
};
