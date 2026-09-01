<?php

use App\Http\Controllers\Api\NetworkAssignmentController;
use App\Http\Controllers\Api\NetworkDeviceController;
use App\Http\Controllers\Api\PopDistribusiController;
use App\Http\Controllers\CustomerPortal\PortalAuthController;
use App\Http\Controllers\CustomerPortal\PortalBalanceController;
use App\Http\Controllers\CustomerPortal\PortalInvoiceController;
use App\Http\Controllers\CustomerPortal\PortalMeController;
use App\Http\Controllers\CustomerPortal\PortalPaymentController;
use App\Http\Controllers\CustomerPortal\PortalStaffKolektorController;
use App\Http\Controllers\CustomerPortal\PortalStaffTicketController;
use App\Http\Controllers\CustomerPortal\PortalTicketController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Baru — Topologi Jaringan & Konfirmasi Assignment
|--------------------------------------------------------------------------
|
| docs/api/api-pop-distribusi/. Arah MASUK — Website B yang connect ke sini,
| kebalikan dari api-webhook-pemasangan (murni outbound, TIDAK PUNYA route
| di sini sama sekali — jangan dicampur). Tiga endpoint, dua token bearer
| (baca vs tulis, keputusan.md §5) — network-assignment & network-device
| BERBAGI token tulis yang sama (satu kelas risiko: sama-sama menulis data
| pelanggan), tapi rate limiter TERPISAH per endpoint (keputusan.md §19)
| supaya kegagalan beruntun di satu endpoint tidak memengaruhi yang lain.
|
*/

Route::prefix('v1')->group(function () {
    Route::middleware(['pop_distribusi.read', 'throttle:pop-distribusi-read'])
        ->get('/pop-distribusi', [PopDistribusiController::class, 'index'])
        ->name('api.pop-distribusi.index');

    Route::middleware(['network_assignment.write', 'throttle:network-assignment-write'])
        ->post('/installations/network-assignment', [NetworkAssignmentController::class, 'store'])
        ->name('api.installations.network-assignment');

    Route::middleware(['network_assignment.write', 'throttle:network-device-write'])
        ->post('/installations/network-device', [NetworkDeviceController::class, 'store'])
        ->name('api.installations.network-device');
});

/*
|--------------------------------------------------------------------------
| API Portal Pelanggan (docs/api/api-portal-pelanggan/)
|--------------------------------------------------------------------------
|
| Prefix /api/customer-portal, BUKAN /api/v1 — kontrak dipegang tim portal
| eksternal (keputusan.md §1, business-logic.md:8), jangan diseragamkan ke
| pola pop-distribusi di atas. /ping = health-check permanen, tanpa
| proteksi token (data kosong, risiko disclosure nol) — dipakai buat
| verifikasi server hidup + CORS + JSON error sebelum auth ada. Fase 2+
| menambah /auth/*, /me/*: middleware X-Portal-Client tinggal disisip ke
| array middleware grup ini, bukan restrukturisasi baru.
|
*/

Route::prefix('customer-portal')->middleware(['throttle:customer-portal-api'])->group(function () {
    Route::get('/ping', fn () => response()->json(['data' => ['status' => 'ok']]))
        ->name('api.customer-portal.ping');

    // Fase 2 — semua route di bawah ini butuh client secret portal
    // (lapis pertama). Token per-pelanggan (lapis kedua) disisip per-route
    // yang memang butuh identitas pelanggan — /auth/login, /auth/claim, dan
    // /auth/refresh justru BELUM punya identitas pelanggan saat dipanggil.
    Route::middleware(['portal_client'])->group(function () {
        Route::prefix('auth')->group(function () {
            Route::post('/login', [PortalAuthController::class, 'login'])
                ->middleware(['throttle:customer-portal-auth', 'throttle:customer-portal-auth-ip'])
                ->name('api.customer-portal.auth.login');

            Route::post('/claim', [PortalAuthController::class, 'claim'])
                ->middleware(['throttle:customer-portal-auth', 'throttle:customer-portal-auth-ip'])
                ->name('api.customer-portal.auth.claim');

            // BUKAN 'portal_token' — refresh token beda jenis dari access
            // token, diverifikasi manual di controller
            // (CustomerPortalToken::resolveRefreshToken()).
            Route::post('/refresh', [PortalAuthController::class, 'refresh'])
                ->name('api.customer-portal.auth.refresh');

            Route::post('/logout', [PortalAuthController::class, 'logout'])
                ->middleware(['portal_token'])
                ->name('api.customer-portal.auth.logout');

            Route::post('/logout-all', [PortalAuthController::class, 'logoutAll'])
                ->middleware(['portal_token'])
                ->name('api.customer-portal.auth.logout-all');
        });

        // Resolusi QR (2026-08-27) — dipanggil Portal SEBELUM pelanggan
        // punya token apa pun (belum login/claim), jadi bukan bagian grup
        // 'auth' (yang isinya kredensial) ATAU grup 'portal_token' (yang
        // butuh Bearer). Cuma client secret + limiter qr-public (sama kelas
        // risiko dengan QrScanController::dispatch(), baca doang, gak ada
        // kredensial yang bisa ditebak).
        Route::middleware(['throttle:qr-public'])
            ->get('/qr/resolve', [PortalAuthController::class, 'resolveQr'])
            ->name('api.customer-portal.qr.resolve');

        Route::middleware(['portal_token'])->group(function () {
            Route::get('/me', [PortalMeController::class, 'show'])
                ->name('api.customer-portal.me.show');

            Route::put('/me/password', [PortalMeController::class, 'updatePassword'])
                ->middleware(['throttle:customer-portal-auth', 'throttle:customer-portal-auth-ip'])
                ->name('api.customer-portal.me.update-password');

            // Fase 3 — tagihan, pembayaran, kwitansi, saldo. Rate limiter
            // cukup customer-portal-api (attached di level grup atas) —
            // ini endpoint data biasa, bukan kredensial.
            Route::get('/me/invoices', [PortalInvoiceController::class, 'index'])
                ->name('api.customer-portal.me.invoices.index');

            Route::get('/me/invoices/{invoice_number}', [PortalInvoiceController::class, 'show'])
                ->name('api.customer-portal.me.invoices.show');

            Route::get('/me/payments', [PortalPaymentController::class, 'index'])
                ->name('api.customer-portal.me.payments.index');

            Route::get('/me/payments/{payment_number}/receipt', [PortalPaymentController::class, 'receipt'])
                ->name('api.customer-portal.me.payments.receipt');

            Route::get('/me/payments/{payment_number}/receipt.pdf', [PortalPaymentController::class, 'receiptPdf'])
                ->name('api.customer-portal.me.payments.receipt-pdf');

            Route::get('/me/payments/{payment_number}/receipt-view', [PortalPaymentController::class, 'receiptView'])
                ->name('api.customer-portal.me.payments.receipt-view');

            Route::get('/me/balance', [PortalBalanceController::class, 'show'])
                ->name('api.customer-portal.me.balance');

            Route::get('/me/tickets', [PortalTicketController::class, 'index'])
                ->name('api.customer-portal.me.tickets.index');

            Route::get('/me/tickets/{ticket_number}', [PortalTicketController::class, 'show'])
                ->name('api.customer-portal.me.tickets.show');
        });

        // Staf/kolektor (2026-08-29, docs/plan/qr-code/
        // analisa-unifikasi-qr-staff-portal.md §2) — SUBJEK BEDA dari grup
        // portal_token di atas (staf, bukan pelanggan), makanya middleware
        // token-nya juga beda (`portal_staff_token:tickets`, bukan
        // `portal_token`). Path-locked ke sini SENGAJA: token yang sama
        // tidak lolos dipanggil ke endpoint /me/* pelanggan atau sebaliknya.
        Route::middleware(['portal_staff_token:tickets'])->group(function () {
            Route::get('/tickets/options', [PortalStaffTicketController::class, 'options'])
                ->name('api.customer-portal.tickets.options');

            Route::post('/tickets', [PortalStaffTicketController::class, 'store'])
                ->name('api.customer-portal.tickets.store');
        });

        Route::middleware(['portal_staff_token:kolektor'])->prefix('kolektor')->group(function () {
            Route::get('/worklist/{code}', [PortalStaffKolektorController::class, 'worklist'])
                ->name('api.customer-portal.kolektor.worklist');

            Route::post('/payments', [PortalStaffKolektorController::class, 'payments'])
                ->name('api.customer-portal.kolektor.payments');
        });
    });
});
