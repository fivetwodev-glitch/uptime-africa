<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Réserve l'espace agence aux membres d'une agence ;
 * l'administrateur général est renvoyé vers son espace.
 */
class EnsureAgencyUser
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->isSuperAdmin()) {
            return redirect()->route('admin.index');
        }

        return $next($request);
    }
}
