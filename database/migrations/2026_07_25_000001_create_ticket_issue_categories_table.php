<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_issue_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('default_priority', 20);

            // Klasifikasi/pelaporan saja — TIDAK mengubah mesin SLA. SLA Pengerjaan
            // tetap di TaskService/PackageSlaSetting, Handling SLA tetap di
            // fop_tasks.handling_sla_hours. Lihat docs/plan/RANCANGAN_MASTER_ISSUE_TICKETING.md.
            $table->enum('sla_source', ['paket', 'prioritas'])->default('prioritas');

            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_issue_categories');
    }
};
