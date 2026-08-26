<?php

namespace App\Http\Controllers\CustomerPortal;

use App\Http\Controllers\Controller;
use App\Http\Requests\CustomerPortal\PortalLoginRequest;
use App\Http\Requests\CustomerPortal\PortalRefreshRequest;
use App\Services\CustomerPortal\PortalAuthService;
use Dedoc\Scramble\Attributes\BodyParameter;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Auth portal pelanggan (docs/api/api-portal-pelanggan/, Fase 2). Semua
 * route di controller ini SUDAH di belakang middleware `portal_client`
 * (client secret) — lihat routes/api.php.
 *
 * Controller tipis — business logic ada di {@see PortalAuthService}
 * (diekstrak 2026-08-25, CLAUDE.md "Pembagian layer"). Method di sini cuma
 * ambil input, panggil Service, dan memetakan outcome ke status HTTP —
 * tidak ada aturan bisnis yang ditulis di sini.
 */
#[Group('Portal Pelanggan', 'Endpoint bagi aplikasi Portal Pelanggan (domain terpisah, tanpa kredensial DB operasional) untuk kredensial, tagihan, pembayaran, kwitansi, saldo, dan riwayat ticketing pelanggan. Semua permintaan WAJIB header `X-Portal-Client` (client secret statis); endpoint `/me/*` tambah `Authorization: Bearer <access_token>`.')]
class PortalAuthController extends Controller
{
    public function __construct(private readonly PortalAuthService $service) {}

    /**
     * Login
     *
     * `login_id` + password → pasangan access token (15 menit) dan refresh
     * token (30 hari, rotating).
     */
    #[BodyParameter('login_id', description: 'Login ID pelanggan, format {registration_prefix}-{customer_code}.', example: 'PNG-RQ000631')]
    #[BodyParameter('password', description: 'Password akun portal.', example: 'Kuda-Nil-Rajin-88')]
    #[Response(200, description: 'Login berhasil.', examples: [[
        'access_token' => '<64 karakter acak>',
        'refresh_token' => '<64 karakter acak>',
        'token_type' => 'Bearer',
        'expires_in' => 900,
    ]])]
    #[Response(401, description: 'Login ID atau password salah — pesan SAMA PERSIS baik akun tidak ada maupun password salah, sengaja tidak dibedakan.')]
    #[Response(423, description: 'Akun terkunci sementara (5x gagal berturut-turut, lockout 15 menit).')]
    #[Response(429, description: 'Jumlah percobaan melebihi batas (5/15menit per IP+login_id, atau 30/15menit per IP).')]
    public function login(PortalLoginRequest $request): JsonResponse
    {
        $result = $this->service->login(
            $request->string('login_id')->toString(),
            $request->string('password')->toString(),
            $request->ip(),
        );

        return match ($result['outcome']) {
            'success' => response()->json($result['tokens']),
            'locked' => response()->json(['message' => 'Akun terkunci sementara, coba lagi nanti.'], 423),
            default => $this->respondInvalidCredentials(),
        };
    }

    /**
     * Klaim akun (STUB)
     *
     * Belum aktif — menunggu modul QR/PIN pelanggan
     * (docs/plan/qr-code/rancangan-qr-pelanggan-final.md §7.6). Modul itu
     * nol kode DAN nol keputusan operasional (threat-model ONT, logistik
     * cetak belum diputuskan pemilik produk) — keputusan user 2026-08-24:
     * fondasi Fase 2 lain dibangun penuh, endpoint ini ditahan sebagai stub
     * sampai modul QR/PIN kelar. Rate limiter TETAP terpasang di route
     * (routes/api.php) supaya begitu diaktifkan nanti tidak lupa
     * memasangnya kembali. Selalu membalas 501.
     */
    #[Response(501, description: 'Belum tersedia — menunggu modul QR/PIN pelanggan.', examples: [[
        'message' => 'Klaim akun portal belum tersedia — menunggu modul QR/PIN pelanggan.',
    ]])]
    public function claim(Request $request): JsonResponse
    {
        return response()->json([
            'message' => 'Klaim akun portal belum tersedia — menunggu modul QR/PIN pelanggan.',
        ], 501);
    }

    /**
     * Refresh token
     *
     * Menukar refresh token yang masih berlaku dengan pasangan token baru
     * (rotasi sekali pakai). Refresh token yang SUDAH dipakai lalu dipakai
     * lagi mencabut SEMUA token pelanggan itu — indikasi pencurian.
     */
    #[BodyParameter('refresh_token', description: 'Refresh token yang diterima dari /auth/login atau /auth/refresh sebelumnya.', example: '<64 karakter acak>')]
    #[Response(200, description: 'Rotasi berhasil, pasangan token baru diterbitkan.', examples: [[
        'access_token' => '<64 karakter acak>',
        'refresh_token' => '<64 karakter acak>',
        'token_type' => 'Bearer',
        'expires_in' => 900,
    ]])]
    #[Response(401, description: 'Sesi tidak valid — token tidak ditemukan, kedaluwarsa, atau reuse (sudah pernah dipakai sebelumnya).')]
    public function refresh(PortalRefreshRequest $request): JsonResponse
    {
        $result = $this->service->refresh(
            $request->string('refresh_token')->toString(),
            $request->ip(),
        );

        return match ($result['outcome']) {
            'success' => response()->json($result['tokens']),
            default => response()->json(['message' => 'Sesi tidak valid, silakan login ulang.'], 401),
        };
    }

    /**
     * Logout
     *
     * Mencabut SEMUA token pelanggan itu — perilakunya SAMA dengan
     * logout-all (access token tidak punya rantai ke refresh pasangannya
     * tanpa client kirim refresh_token tambahan, keputusan 2026-08-25).
     */
    #[Response(200, description: 'Berhasil keluar.', examples: [['message' => 'Berhasil keluar.']])]
    #[Response(401, description: 'Access token tidak disertakan atau tidak valid.')]
    public function logout(Request $request): JsonResponse
    {
        $this->service->logout((int) $request->attributes->get('portal_customer_id'));

        return response()->json(['message' => 'Berhasil keluar.']);
    }

    /**
     * Logout semua perangkat
     *
     * Mencabut SEMUA token pelanggan itu.
     */
    #[Response(200, description: 'Berhasil keluar dari semua perangkat.', examples: [['message' => 'Berhasil keluar dari semua perangkat.']])]
    #[Response(401, description: 'Access token tidak disertakan atau tidak valid.')]
    public function logoutAll(Request $request): JsonResponse
    {
        $this->service->logoutAll((int) $request->attributes->get('portal_customer_id'));

        return response()->json(['message' => 'Berhasil keluar dari semua perangkat.']);
    }

    private function respondInvalidCredentials(): JsonResponse
    {
        return response()->json(['message' => 'Login ID atau password salah.'], 401);
    }
}
