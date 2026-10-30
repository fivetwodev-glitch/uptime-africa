<?php

namespace App\Http\Middleware;

use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fixe l'agence courante d'après l'utilisateur connecté.
 * L'administrateur général n'a pas d'agence : aucun filtrage.
 */
class SetTenantContext
{
    public function __construct(private TenantContext $tenant) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->isSuperAdmin()) {
            if ($user->agency_id === null || ! $user->agency?->is_active) {
                abort(403, 'Votre agence est inactive ou introuvable.');
            }

            $this->tenant->set($user->agency_id);
            Context::add('agency_id', $user->agency_id);
        }

        if ($user) {
            Context::add('user_id', $user->id);
        }

        return $next($request);
    }
}
