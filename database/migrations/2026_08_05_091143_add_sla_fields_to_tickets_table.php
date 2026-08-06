<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `tickets.sla_hours` + `tickets.sla_deadline_at` — snapshot Handling SLA
 * di titik tiket LAHIR, bukan cuma di FopTask (lihat
 * docs/plan/analisa-target-sla-ticketing.md). Sama prinsip snapshot kayak
 * `fop_tasks.handling_sla_hours`: gak ikut geser kalau paket pelanggan atau
 * Master Timeline SLA diubah admin belakangan.
 *
 * Kenapa dua kolom (bukan cuma deadline): `sla_hours` dipakai FopTask
 * mewarisi angka yang SAMA persis saat tiket dieskalasi (TicketService::
 * syncToFopTask()) — deadline gak reset di titik handoff, satu clock jalan
 * terus lintas modul Ticketing → FOP.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->unsignedSmallInteger('sla_hours')->nullable()->after('priority');
            $table->timestamp('sla_deadline_at')->nullable()->after('sla_hours');
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn(['sla_hours', 'sla_deadline_at']);
        });
    }
};
