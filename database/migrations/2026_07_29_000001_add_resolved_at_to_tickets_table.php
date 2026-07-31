<?php

use App\Enums\TaskStatus;
use App\Enums\TicketHandler;
use App\Enums\TicketHandlingStatus;
use App\Enums\TicketHistoryAction;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `tickets.resolved_at` — kapan keluhan pelanggan BENAR-BENAR beres.
 *
 * Nilainya datang dari dua sumber berbeda (lihat
 * docs/plan/analisa-halaman-history-ticketing.md §4.1):
 *   - Ditutup Helpdesk/NOC sendiri → saat TicketService::close() jalan.
 *   - Lewat FOP                    → `tasks.completed_at` (waktu teknisi lapor),
 *                                    BUKAN waktu tiket ditutup — teknisi punya
 *                                    jalur lapor-sekarang & lapor-nanti.
 *
 * Sengaja didenormalisasi ke kolom sendiri, bukan dihitung subquery per baris:
 * halaman History butuh sortir + rata-rata durasi di atas ribuan baris.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->timestamp('resolved_at')->nullable()->after('noc_checked_at');

            // Halaman History selalu difilter POP (applyUserScope) lalu
            // disortir/di-range per tanggal selesai.
            $table->index(['pop_id', 'resolved_at'], 'tickets_pop_resolved_idx');
        });

        $this->backfillInternal();
        $this->backfillFop();
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropIndex('tickets_pop_resolved_idx');
            $table->dropColumn('resolved_at');
        });
    }

    /**
     * Tiket yang ditutup Helpdesk/NOC sendiri — waktunya diambil dari jejak
     * `ticket_histories` action `diselesaikan`. Dipecah per-chunk (bukan satu
     * UPDATE ... JOIN) supaya jalan sama persis di sqlite (test) dan MySQL.
     */
    private function backfillInternal(): void
    {
        DB::table('tickets')
            ->whereNull('resolved_at')
            ->where('status', TicketHandlingStatus::CLOSED->value)
            ->whereIn('handler', [TicketHandler::HELPDESK->value, TicketHandler::NOC->value])
            ->select('id')
            ->orderBy('id')
            ->chunk(500, function ($tickets) {
                foreach ($tickets as $ticket) {
                    $closedAt = DB::table('ticket_histories')
                        ->where('ticket_id', $ticket->id)
                        ->where('action', TicketHistoryAction::DISELESAIKAN->value)
                        ->orderByDesc('happened_at')
                        ->value('happened_at');

                    if ($closedAt) {
                        DB::table('tickets')->where('id', $ticket->id)->update(['resolved_at' => $closedAt]);
                    }
                }
            });
    }

    /**
     * Tiket jalur FOP — waktunya `tasks.completed_at`. Fallback ke
     * `fop_tasks.updated_at` cuma kalau FopTask-nya udah `selesai` tapi Task
     * eksekusinya gak ada/gak punya completed_at (data lama pra-sync); itu
     * perkiraan, bukan waktu lapor asli.
     */
    private function backfillFop(): void
    {
        DB::table('tickets')
            ->whereNull('tickets.resolved_at')
            ->where('tickets.handler', TicketHandler::FOP->value)
            ->whereNotNull('tickets.fop_task_id')
            ->select('tickets.id', 'tickets.fop_task_id')
            ->orderBy('tickets.id')
            ->chunk(500, function ($tickets) {
                foreach ($tickets as $ticket) {
                    $fopTask = DB::table('fop_tasks')->where('id', $ticket->fop_task_id)->first();

                    if (! $fopTask || $fopTask->status !== TaskStatus::SELESAI->value) {
                        continue;
                    }

                    $completedAt = $fopTask->task_id
                        ? DB::table('tasks')->where('id', $fopTask->task_id)->value('completed_at')
                        : null;

                    DB::table('tickets')
                        ->where('id', $ticket->id)
                        ->update(['resolved_at' => $completedAt ?: $fopTask->updated_at]);
                }
            });
    }
};
