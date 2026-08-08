<?php

use App\Enums\TicketHandler;
use App\Enums\TicketHandlingStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Mekanisme Close/Escalate (docs/plan/RANCANGAN_WORKSHEET_TICKETING.MD) —
 * FopTask sekarang BUKAN lagi auto-dibuat saat tiket disubmit. Tiket mulai di
 * tangan Helpdesk, boleh diselesaikan sendiri, dieskalasi ke NOC, atau
 * dieskalasi ke FOP (baru di titik ini FopTask kebentuk). Lihat
 * TicketService::create()/close()/escalateToNoc()/escalateToFop().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->string('handler', 20)->default(TicketHandler::HELPDESK->value)->after('issue_category_id');
            $table->string('status', 20)->default(TicketHandlingStatus::OPEN->value)->after('handler');
        });

        // Tiket lama (sebelum migration ini) semuanya lahir dari alur auto-sync
        // lama — SEMUA udah/pernah punya FopTask (termasuk yang fop_task_id-nya
        // sekarang null krn FopTask-nya dihapus FOP = orphan/"Terputus"). Backfill
        // handler=fop biar Ticket::bucket() tetap baca lewat jalur FopTask-status
        // yang sama kayak sebelumnya, gak ada tiket lama yang statusnya berubah.
        DB::table('tickets')->update(['handler' => TicketHandler::FOP->value]);
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn(['handler', 'status']);
        });
    }
};
