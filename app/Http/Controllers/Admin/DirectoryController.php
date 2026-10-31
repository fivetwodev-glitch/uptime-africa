<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Models\Agency;
use App\Models\AuditLog;
use App\Models\Incident;
use App\Models\Site;
use App\Models\User;
use App\Support\Sessions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Super Admin : utilisateurs, sites, incidents et journal des actions de toutes les agences (lecture). */
class DirectoryController extends Controller
{
    public function users(Request $request): View
    {
        $this->authorize('admin.access');
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:100'], 'agence' => ['nullable', 'string', 'max:26']]);

        return view('admin.users', [
            'users' => User::with(['agency:id,name', 'role:id,slug,name'])
                ->when($filters['q'] ?? null, fn ($q, $term) => $q->where(fn ($w) => $w->where('name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%")))
                ->when($filters['agence'] ?? null, fn ($q, $id) => $q->where('agency_id', $id))
                ->orderBy('name')->paginate(30)->withQueryString(),
            'filters' => $filters,
            'agencies' => Agency::orderBy('name')->get(['id', 'name']),
            'me' => $request->user(),
        ]);
    }

    /** Désactiver un compte : connexion refusée et sessions fermées immédiatement. */
    public function toggleUser(Request $request, User $user): RedirectResponse
    {
        $this->authorize('admin.access');

        if ($user->is($request->user())) {
            throw ValidationException::withMessages(['user' => 'Vous ne pouvez pas désactiver votre propre compte.']);
        }
        if ($user->is_active && $user->hasRole(RoleName::SuperAdmin)
            && User::where('is_active', true)->whereHas('role', fn ($q) => $q->where('slug', RoleName::SuperAdmin->value))->count() <= 1) {
            throw ValidationException::withMessages(['user' => 'Il doit rester au moins un super administrateur actif.']);
        }

        $user->is_active = ! $user->is_active;
        $user->save();
        if (! $user->is_active) {
            Sessions::revokeAll($user);
        }
        AuditLog::record($user->is_active ? 'user.reactivated_by_admin' : 'user.deactivated_by_admin', $user, ['email' => $user->email]);

        return back()->with('status', $user->is_active ? "Compte {$user->email} réactivé." : "Compte {$user->email} désactivé et déconnecté.");
    }

    public function sites(Request $request): View
    {
        $this->authorize('admin.access');
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:100'], 'agence' => ['nullable', 'string', 'max:26']]);

        return view('admin.sites', [
            'sites' => Site::with(['agency:id,name', 'client:id,name'])
                ->when($filters['q'] ?? null, fn ($q, $term) => $q->where(fn ($w) => $w->where('name', 'like', "%{$term}%")->orWhere('url', 'like', "%{$term}%")))
                ->when($filters['agence'] ?? null, fn ($q, $id) => $q->where('agency_id', $id))
                ->orderBy('name')->paginate(30)->withQueryString(),
            'filters' => $filters,
            'agencies' => Agency::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function incidents(Request $request): View
    {
        $this->authorize('admin.access');
        $filters = $request->validate(['etat' => ['nullable', 'in:ouverts,resolus'], 'agence' => ['nullable', 'string', 'max:26']]);

        return view('admin.incidents', [
            'incidents' => Incident::with(['site:id,name,url,agency_id', 'site.agency:id,name'])
                ->when(($filters['etat'] ?? null) === 'ouverts', fn ($q) => $q->whereNull('resolved_at'))
                ->when(($filters['etat'] ?? null) === 'resolus', fn ($q) => $q->whereNotNull('resolved_at'))
                ->when($filters['agence'] ?? null, fn ($q, $id) => $q->where('agency_id', $id))
                ->latest('started_at')->paginate(30)->withQueryString(),
            'filters' => $filters,
            'agencies' => Agency::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function audit(Request $request): View
    {
        $this->authorize('admin.access');
        $filters = $request->validate(['action' => ['nullable', 'string', 'max:60'], 'agence' => ['nullable', 'string', 'max:26']]);

        return view('admin.audit', [
            'logs' => AuditLog::query()
                ->when($filters['action'] ?? null, fn ($q, $a) => $q->where('action', 'like', "{$a}%"))
                ->when($filters['agence'] ?? null, fn ($q, $id) => $q->where('agency_id', $id))
                ->latest('id')->paginate(50)->withQueryString(),
            'filters' => $filters,
            'agencies' => Agency::orderBy('name')->pluck('name', 'id'),
            'users' => User::pluck('email', 'id'),
        ]);
    }
}
