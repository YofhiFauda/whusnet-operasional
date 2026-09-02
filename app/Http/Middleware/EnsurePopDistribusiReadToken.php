<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Token bearer BACA-SAJA untuk `GET /api/v1/pop-distribusi`
 * (docs/api/api-pop-distribusi/business-logic.md). Kelas TERPISAH dari
 * {@see EnsureNetworkAssignmentWriteToken} — dua token beda kelas risiko,
 * jangan digabung jadi satu middleware ber-parameter (keputusan.md §5): kalau
 * token baca bocor, dampaknya cuma expose struktur topologi, TIDAK ikut
 * membuka jalur tulis.
 */
class EnsurePopDistribusiReadToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = config('webhooks.pop_distribusi_read_token');
        $given = $request->bearerToken();

        if (! $expected || ! $given || ! hash_equals($expected, $given)) {
            abort(401, 'Token tidak valid.');
        }

        return $next($request);
    }
}
