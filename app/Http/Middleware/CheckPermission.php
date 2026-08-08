<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        if (! auth()->check()) {
            abort(403, 'Unauthorized action.');
        }

        $user = auth()->user();

        // Support full-access bypass
        if ($user->hasPermission('*')) {
            return $next($request);
        }

        // Support multi-permission OR logic via pipe separator (e.g. 'customers.view|customers.create')
        $permissions = explode('|', $permission);

        foreach ($permissions as $perm) {
            // Check via EffectiveAccessService
            if ($user->hasPermission(trim($perm))) {
                return $next($request);
            }
        }

        abort(403, 'Unauthorized action.');
    }
}
