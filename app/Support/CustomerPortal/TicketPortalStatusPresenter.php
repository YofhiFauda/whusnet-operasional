<?php

namespace App\Support\CustomerPortal;

use App\Enums\TaskStatus;
use App\Enums\TicketHandler;
use App\Enums\TicketHandlingStatus;
use App\Models\Ticket;

/**
 * Status tiket versi pelanggan (docs/api/api-portal-pelanggan/business-logic.md
 * §4, flowchart.md §3) — presenter TERPISAH dari `Ticket::statusLabel()`,
 * yang mengembalikan label staf ("Diproses NOC", "Ditangani Helpdesk") dan
 * istilah internal ("Terputus" untuk orphan) yang tidak berarti apa-apa bagi
 * pelanggan dan membocorkan struktur organisasi.
 *
 * URUTAN PENGECEKAN KRITIS — `handler` DULU, `status` BELAKANGAN. Begitu
 * `handler = FOP`, kolom `tickets.status` BEKU/tidak lagi diperbarui —
 * status sesungguhnya wajib ditarik dari `Ticket::resolveStatus()` (baca
 * `FopTask::status`). Membalik urutan ini (cek `status` dulu) akan salah
 * untuk tiket pasca-FOP yang sudah lama selesai di lapangan: `status`
 * kolomnya tetap `open` sejak sebelum eskalasi, jadi cabang HELPDESK/NOC di
 * bawah akan salah mengklaim "Diterima"/"Sedang Ditangani" selamanya.
 */
class TicketPortalStatusPresenter
{
    /**
     * @return array{value: string, label: string}
     */
    public static function resolve(Ticket $ticket): array
    {
        if ($ticket->handler === TicketHandler::FOP) {
            return match ($ticket->resolveStatus()) {
                TaskStatus::SELESAI => ['value' => 'selesai', 'label' => 'Selesai'],
                TaskStatus::DIBATALKAN => ['value' => 'dibatalkan', 'label' => 'Dibatalkan'],
                // draft/terjadwal/in_progress/pending, DAN null (FopTask hilang
                // — orphan, Ticket::isOrphan()) sama-sama "Sedang Ditangani".
                // Orphan BUKAN "Terputus" — itu kegagalan data internal kita,
                // memindahkannya ke layar pelanggan tidak menolong siapa pun.
                default => ['value' => 'sedang_ditangani', 'label' => 'Sedang Ditangani'],
            };
        }

        // handler != FOP — DI SINI baru tickets.status bermakna.
        if ($ticket->status === TicketHandlingStatus::CLOSED) {
            return ['value' => 'selesai', 'label' => 'Selesai'];
        }

        if ($ticket->status === TicketHandlingStatus::CANCELLED) {
            return ['value' => 'dibatalkan', 'label' => 'Dibatalkan'];
        }

        if ($ticket->handler === TicketHandler::NOC) {
            return ['value' => 'sedang_ditangani', 'label' => 'Sedang Ditangani'];
        }

        // handler=HELPDESK, status=open — satu-satunya kombinasi tersisa.
        return ['value' => 'diterima', 'label' => 'Diterima'];
    }
}
