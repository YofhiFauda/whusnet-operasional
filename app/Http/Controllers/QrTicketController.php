<?php

namespace App\Http\Controllers;

use App\Services\CustomerQrTokenService;
use App\Services\EffectiveAccessService;
use Illuminate\Http\RedirectResponse;

/**
 * Fungsi B — Ticketing (docs/plan/qr-code/rancangan-qr-pelanggan-final.md
 * §6.2, §10 Fase 1). Route `/q1/{code}/tiket` di belakang middleware `auth`
 * ASLI (routes/web.php) — bukan cuma pengecekan `auth()->check()` di
 * QrScanController, defense-in-depth kedua kalau cabang pertama somehow
 * ke-bypass.
 *
 * QR di sini CUMA cara mengisi field pelanggan lebih cepat. Setelahnya
 * masuk TicketService::create() apa adanya lewat tickets.store — TIDAK ADA
 * perubahan pada alur sinkronisasi Ticket ↔ FopTask ↔ Task.
 */
class QrTicketController extends Controller
{
    public function __construct(private readonly CustomerQrTokenService $qrTokens) {}

    public function create(string $code): RedirectResponse
    {
        abort_unless(auth()->user()->hasPermission('tickets.create'), 403);

        [$token, $signature] = array_pad(explode('.', $code, 2), 2, '');
        $resolution = $this->qrTokens->resolve($token, $signature);

        abort_unless($resolution['status'] === 'success', 404);

        $customer = $resolution['qrToken']->customer;

        $access = app(EffectiveAccessService::class);
        $user = auth()->user();

        abort_unless(
            $access->hasAllPopAccess($user) || in_array((int) $customer->pop_id, $access->getAllowedPopIds($user), true),
            403,
            'Anda tidak memiliki akses ke pelanggan di POP ini.'
        );

        return redirect()->route('tickets.create', ['customer_id' => $customer->id]);
    }
}
