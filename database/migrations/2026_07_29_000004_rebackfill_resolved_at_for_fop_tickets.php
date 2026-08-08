<?php

use App\Enums\TicketHandler;
use App\Enums\TicketHistoryAction;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Perbaiki `tickets.resolved_at` untuk tiket jalur FOP.
 *
 * Migrasi `2026_07_29_000001` mengisinya dari `tasks.completed_at` (waktu
 * teknisi lapor). Aturannya berubah beberapa jam kemudian: History Ticketing
 * berhenti di titik penyerahan ke FOP — progres lapangan bukan urusan modul
 * Ticketing. Jadi buat tiket `handler=fop`, `resolved_at` sekarang berarti
 * **kapan tiket diserahkan**, yaitu `happened_at` baris riwayat `dieskalasi` →
 * `fop`.
 *
 * Fallback ke `created_at` cuma buat tiket yang lahir langsung di tangan FOP
 * (disubmit dari halaman Task FOP, `$fopOrigin`) — tiket itu memang gak punya
 * baris riwayat eskalasi karena gak pernah mampir ke meja Helpdesk.
 *
 * Tiket jalur internal (closed/cancelled oleh Helpdesk/NOC) TIDAK disentuh.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('tickets')
            ->where('handler', TicketHandler::FOP->value)
            ->select('id', 'created_at')
            ->orderBy('id')
            ->chunk(500, function ($tickets) {
                foreach ($tickets as $ticket) {
                    $handoverAt = DB::table('ticket_histories')
                        ->where('ticket_id', $ticket->id)
                        ->where('action', TicketHistoryAction::DIESKALASI->value)
                        ->where('to_status', TicketHandler::FOP->value)
                        ->orderBy('happened_at')
                        ->value('happened_at');

                    DB::table('tickets')
                        ->where('id', $ticket->id)
                        ->update(['resolved_at' => $handoverAt ?: $ticket->created_at]);
                }
            });
    }

    /**
     * Gak bisa dibalikin ke nilai lama (`tasks.completed_at`) tanpa menebak —
     * dan nilai lama itu justru yang salah menurut aturan sekarang. Sengaja
     * no-op, bukan pura-pura reversible.
     */
    public function down(): void {}
};
