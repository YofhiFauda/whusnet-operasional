<?php

namespace App\Http\Resources\CustomerPortal;

use App\Http\Resources\ApiResource;
use App\Support\CustomerPortal\TicketPortalStatusPresenter;
use Illuminate\Http\Request;

/**
 * `GET /me/tickets`, `/me/tickets/{ticket_number}`
 * (docs/api/api-portal-pelanggan/business-logic.md §4) — whitelist ketat.
 *
 * `catatan_teknis`, `handler`/`status` mentah, `fop_task_id`, nomor
 * `TFOP-`/`TASK-`, riwayat mentah + nama pegawai, lampiran, koordinat, dan
 * snapshot perangkat SEMUA haram — TIDAK ADA satu pun di `toArray()` ini.
 * Status lewat `TicketPortalStatusPresenter::resolve()`, BUKAN
 * `Ticket::statusLabel()` (bocorin struktur organisasi internal).
 */
class TicketResource extends ApiResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'ticket_number' => $this->ticket_number,
            'created_at' => $this->created_at?->toIso8601String(),
            'issue_category' => $this->issueCategory?->name,
            'detail_keluhan' => $this->detail_keluhan,
            'status' => TicketPortalStatusPresenter::resolve($this->resource),
            'resolved_at' => $this->resolved_at?->toIso8601String(),
        ];
    }
}
