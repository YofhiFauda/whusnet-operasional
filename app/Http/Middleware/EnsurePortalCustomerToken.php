<?php

namespace App\Http\Middleware;

use App\Models\CustomerPortalToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lapis KEDUA dari dua kredensial di jalur portal pelanggan — membuktikan
 * "ini pelanggan X" (bukan "ini portal resmi", itu
 * {@see EnsurePortalClientSecret}, lapis pertama).
 *
 * Middleware PERTAMA di repo ini yang resolve kredensial lewat DB lookup,
 * bukan token statis dari config() seperti EnsurePopDistribusiReadToken /
 * EnsureNetworkAssignmentWriteToken / EnsurePortalClientSecret — tidak ada
 * precedent langsung untuk dicontoh (business-logic.md §Token,
 * flowchart.md §2).
 *
 * `customer_id` HANYA pernah datang dari token yang sudah diverifikasi di
 * sini — endpoint di belakang middleware ini TIDAK PERNAH menerima
 * `customer_id` dari request (query/body/header). Ini aturan tunggal yang
 * menutup IDOR lintas pelanggan (business-logic.md §Kepemilikan data).
 */
class EnsurePortalCustomerToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $bearer = $request->bearerToken();

        if (! $bearer) {
            abort(401, 'Token tidak diberikan.');
        }

        $token = CustomerPortalToken::resolveActiveAccessToken($bearer);

        if (! $token) {
            abort(401, 'Token tidak valid atau sudah kedaluwarsa.');
        }

        // saveQuietly() — stempel pemakaian bukan event yang perlu didengar
        // observer/listener mana pun, tidak perlu memicu siklus model penuh.
        $token->forceFill(['last_used_at' => now()])->saveQuietly();

        $request->attributes->set('portal_customer_id', $token->customer_id);
        $request->attributes->set('portal_token', $token);

        return $next($request);
    }
}
