<?php

namespace App\Http\Controllers\CustomerPortal;

use App\Http\Controllers\Controller;
use App\Http\Requests\CustomerPortal\PortalClaimRequest;
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
     * Klaim akun
     *
     * Diaktifkan 2026-08-26 setelah modul QR/PIN pelanggan (Fase 2) jalan
     * (docs/plan/qr-code/rancangan-qr-pelanggan-final.md §6.6.5). Login ID +
     * PIN dari kartu pelanggan → password baru pilihan sendiri. Akun
     * `customer_portal_accounts` HARUS sudah ada berstatus `pending_claim`
     * (lihat `customers:backfill-portal-login-id`) — endpoint ini TIDAK
     * membuat baris akun dari nol.
     */
    #[BodyParameter('login_id', description: 'Login ID pelanggan, format {registration_prefix}-{customer_code}, sama seperti tercetak di kartu.', example: 'PNG-RQ000631')]
    #[BodyParameter('pin', description: 'PIN 6 digit dari kartu pelanggan — kunci klaim SEKALI PAKAI, bukan kredensial portal seterusnya.', example: '482917')]
    #[BodyParameter('new_password', description: 'Password portal pilihan pelanggan, minimal 10 karakter.', example: 'Kuda-Nil-Rajin-88')]
    #[Response(200, description: 'Klaim berhasil, akun aktif.', examples: [[
        'access_token' => '<64 karakter acak>',
        'refresh_token' => '<64 karakter acak>',
        'token_type' => 'Bearer',
        'expires_in' => 900,
    ]])]
    #[Response(401, description: 'Login ID/PIN salah, atau pelanggan belum punya token QR aktif — pesan SAMA PERSIS untuk semua kasus itu.')]
    #[Response(409, description: 'Akun ini sudah pernah diklaim — arahkan ke Lupa Password.')]
    #[Response(423, description: 'PIN terkunci sementara (5x gagal berturut-turut di gerbang QR mana pun, lockout 15 menit).')]
    #[Response(429, description: 'Jumlah percobaan melebihi batas (5/15menit per IP+login_id, atau 30/15menit per IP).')]
    public function claim(PortalClaimRequest $request): JsonResponse
    {
        $result = $this->service->claim(
            $request->string('login_id')->toString(),
            $request->string('pin')->toString(),
            $request->string('new_password')->toString(),
            $request->ip(),
        );

        return match ($result['outcome']) {
            'success' => response()->json($result['tokens']),
            'already_claimed' => response()->json(['message' => 'Akun ini sudah pernah diaktivasi — gunakan Lupa Password.'], 409),
            'locked' => response()->json(['message' => 'PIN terkunci sementara, coba lagi nanti.'], 423),
            default => response()->json(['message' => 'Login ID atau PIN salah.'], 401),
        };
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
