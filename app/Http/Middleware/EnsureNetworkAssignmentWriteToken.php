<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Token bearer TULIS untuk `POST /api/v1/installations/network-assignment`
 * (docs/api/api-pop-distribusi/business-logic.md). Kelas TERPISAH dari
 * {@see EnsurePopDistribusiReadToken} — lihat alasannya di sana.
 */
class EnsureNetworkAssignmentWriteToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = config('webhooks.network_assignment_write_token');
        $given = $request->bearerToken();

        if (! $expected || ! $given || ! hash_equals($expected, $given)) {
            abort(401, 'Token tidak valid.');
        }

        return $next($request);
    }
}
