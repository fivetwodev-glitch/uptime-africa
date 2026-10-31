<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Agency;
use App\Models\AuditLog;
use App\Support\Sessions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

/**
 * Espace super administrateur : vue des agences, suspension / réactivation.
 */
class AdminController extends Controller
{
    public function index(): View
    {
        $this->authorize('admin.access');

        return view('admin.index', [
            'agencies' => Agency::withCount(['users', 'clients', 'sites'])->orderBy('name')->paginate(30),
            'lastHeartbeat' => Cache::get(config('uptime.heartbeat.cache_key')),
        ]);
    }

    /** Suspendre une agence bloque immédiatement l'accès de tous ses utilisateurs (SetTenantContext). */
    public function toggleAgency(Agency $agency): RedirectResponse
    {
        $this->authorize('admin.access');

        $agency->is_active = ! $agency->is_active;
        $agency->save();

        if (! $agency->is_active) {
            $agency->users()->get()->each(fn ($user) => Sessions::revokeAll($user));
        }
        AuditLog::record($agency->is_active ? 'agency.reactivated' : 'agency.suspended', $agency);

        return back()->with('status', $agency->is_active ? "Agence « {$agency->name} » réactivée." : "Agence « {$agency->name} » suspendue.");
    }
}
