<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Token bearer TULIS untuk `POST /api/v1/isolation-cases/execute`
 * (docs/superpowers/specs/2026-11-20-webhook-isolir-web-a-design.md). Pola
 * sama {@see EnsureNetworkAssignmentWriteToken} — token statis dari config,
 * hash_equals, tanpa DB lookup.
 */
class EnsureWebAWriteToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = config('webhooks.web_a_write_token');
        $given = $request->bearerToken();

        if (! $expected || ! $given || ! hash_equals($expected, $given)) {
            abort(401, 'Token tidak valid.');
        }

        return $next($request);
    }
}
