<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lapis PERTAMA dari dua kredensial di jalur portal pelanggan
 * (docs/api/api-portal-pelanggan/business-logic.md §Autentikasi) —
 * membuktikan "ini portal resmi", bukan "ini pelanggan X" (itu
 * {@see EnsurePortalCustomerToken}, lapis kedua). Statis per environment,
 * dicek dari header `X-Portal-Client`, pola sama
 * {@see EnsurePopDistribusiReadToken} (hash_equals, tanpa DB lookup) — dua
 * middleware TERPISAH karena dua kelas kredensial beda concern, bukan
 * digabung jadi satu middleware ber-parameter (keputusan.md §5).
 */
class EnsurePortalClientSecret
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = config('webhooks.portal_client_secret');
        $given = $request->header('X-Portal-Client');

        if (! $expected || ! $given || ! hash_equals($expected, $given)) {
            abort(401, 'Client portal tidak dikenali.');
        }

        return $next($request);
    }
}
