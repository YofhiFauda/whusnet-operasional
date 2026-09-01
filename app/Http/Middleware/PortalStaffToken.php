<?php

namespace App\Http\Middleware;

use App\Services\StaffPortalTokenService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Kredensial STAF di jalur Portal (beda dari `EnsurePortalCustomerToken`,
 * yang membuktikan "ini pelanggan X" — ini membuktikan "ini staf Y").
 * Middleware ini butuh parameter `$purpose` ('tickets'/'kolektor') — token
 * yang diterbitkan buat satu purpose TIDAK BISA dipakai buat purpose lain,
 * walau staf yang sama (docs/plan/qr-code/
 * analisa-unifikasi-qr-staff-portal.md §4).
 *
 * TIDAK mengonsumsi token di sini — baca (`GET worklist`, dedup check tiket)
 * boleh berkali-kali dalam TTL. Konsumsi (`StaffPortalToken::consume()`)
 * WAJIB dipanggil controller SENDIRI setelah aksi PENULISAN berhasil,
 * bukan di middleware — kalau ditaruh di sini, request yang gagal validasi
 * di controller (mis. dedup guard 409) tetap menghanguskan token padahal
 * belum ada apa pun yang tersimpan.
 */
class PortalStaffToken
{
    public function __construct(private readonly StaffPortalTokenService $tokens) {}

    public function handle(Request $request, Closure $next, string $purpose): Response
    {
        $bearer = $request->bearerToken();

        if (! $bearer) {
            abort(401, 'Token staf tidak diberikan.');
        }

        $token = $this->tokens->resolve($bearer, $purpose);

        if (! $token) {
            abort(401, 'Token staf tidak valid, sudah dipakai, atau sudah kedaluwarsa.');
        }

        $request->attributes->set('staff_portal_token', $token);
        $request->attributes->set('staff_portal_user_id', $token->user_id);
        $request->attributes->set('staff_portal_customer_id', $token->customer_id);

        return $next($request);
    }
}
