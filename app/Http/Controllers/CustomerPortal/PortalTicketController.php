<?php

namespace App\Http\Controllers\CustomerPortal;

use App\Http\Controllers\Controller;
use App\Http\Controllers\CustomerPortal\Concerns\ScopedToAuthenticatedCustomer;
use App\Http\Resources\CustomerPortal\TicketResource;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * `GET /me/tickets`, `/me/tickets/{ticket_number}`
 * (docs/api/api-portal-pelanggan/, Fase 4). "Detail tiket + riwayat" di
 * daftar endpoint TIDAK berarti riwayat mentah (`ticket_histories`) — itu
 * eksplisit haram §4 (memuat nama pegawai). `show()` balikin bentuk yang
 * sama dengan item `index()`, cukup satu tiket.
 */
#[Group('Portal Pelanggan', 'Endpoint bagi aplikasi Portal Pelanggan (domain terpisah, tanpa kredensial DB operasional) untuk kredensial, tagihan, pembayaran, kwitansi, saldo, dan riwayat ticketing pelanggan. Semua permintaan WAJIB header `X-Portal-Client` (client secret statis); endpoint `/me/*` tambah `Authorization: Bearer <access_token>`.')]
class PortalTicketController extends Controller
{
    use ScopedToAuthenticatedCustomer;

    /**
     * Riwayat ticketing
     *
     * Status lewat `TicketPortalStatusPresenter::resolve()` (BUKAN
     * `Ticket::statusLabel()` — bocorin struktur organisasi internal).
     */
    #[Response(200, description: 'Riwayat ticketing berhasil diambil.', examples: [[
        'data' => [[
            'ticket_number' => 'TKT-2026-0045',
            'created_at' => '2026-08-20T08:00:00+07:00',
            'issue_category' => 'Internet Lambat',
            'detail_keluhan' => 'Internet lemot sejak kemarin.',
            'status' => ['value' => 'sedang_ditangani', 'label' => 'Sedang Ditangani'],
            'resolved_at' => null,
        ]],
    ]])]
    #[Response(401, description: 'Access token tidak disertakan atau tidak valid.')]
    public function index(Request $request): AnonymousResourceCollection
    {
        $tickets = $this->ticketsQuery($request)
            ->with('issueCategory')
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return TicketResource::collection($tickets);
    }

    /**
     * Detail tiket
     *
     * Bentuk sama seperti item daftar — bukan riwayat mentah
     * (`ticket_histories`), itu eksplisit haram.
     */
    #[Response(200, description: 'Detail tiket berhasil diambil.')]
    #[Response(401, description: 'Access token tidak disertakan atau tidak valid.')]
    #[Response(404, description: 'ticket_number tidak ditemukan atau milik pelanggan lain — sengaja sama, tidak bisa dibedakan dari luar.')]
    public function show(Request $request, string $ticketNumber): TicketResource
    {
        // fopTask WAJIB di-load — TicketPortalStatusPresenter::resolve()
        // butuh ini lewat Ticket::resolveStatus(), TAPI TIDAK PERNAH
        // diekspos langsung ke response (TicketResource tidak menyentuhnya).
        $ticket = $this->ticketsQuery($request)
            ->with(['issueCategory', 'fopTask'])
            ->where('ticket_number', $ticketNumber)
            ->firstOrFail();

        return new TicketResource($ticket);
    }
}
