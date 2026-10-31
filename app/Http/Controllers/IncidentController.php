<?php

namespace App\Http\Controllers;

use App\Enums\IncidentStatus;
use App\Enums\IncidentType;
use App\Models\AuditLog;
use App\Models\Incident;
use App\Models\Site;
use App\Services\Billing\PlanLimits;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class IncidentController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'statut' => ['nullable', Rule::enum(IncidentStatus::class)],
            'type' => ['nullable', Rule::enum(IncidentType::class)],
            'site' => ['nullable', 'string', 'max:26'],
        ]);

        $retention = app(PlanLimits::class)->retentionDays($request->user()->agency_id);
        $incidents = Incident::query()
            ->with('site:id,name,url')
            // Historique limité par la formule ; un incident encore ouvert reste toujours visible.
            ->where(fn ($q) => $q->where('started_at', '>=', now()->subDays($retention))->orWhereNull('resolved_at'))
            ->when($filters['statut'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->when($filters['type'] ?? null, fn ($q, $t) => $q->where('type', $t))
            ->when($filters['site'] ?? null, fn ($q, $s) => $q->where('site_id', $s))
            ->latest('started_at')
            ->paginate(25)
            ->withQueryString();

        return view('incidents.index', [
            'incidents' => $incidents,
            'filters' => $filters,
            'sites' => Site::orderBy('name')->get(['id', 'name']),
            'hasAnyIncident' => $incidents->total() > 0 || Incident::exists(),
        ]);
    }

    public function show(Incident $incident): View
    {
        $incident->load(['site:id,name,url,client_id', 'site.client:id,name', 'acknowledgedBy:id,name']);

        return view('incidents.show', [
            'incident' => $incident,
            'notifications' => $incident->notificationLogs()->latest()->get(),
        ]);
    }

    public function acknowledge(Request $request, Incident $incident): RedirectResponse
    {
        $this->authorize('incidents.acknowledge');

        if ($incident->status !== IncidentStatus::Open) {
            return back()->with('status', 'Cet incident n\'est plus en attente de prise en charge.');
        }

        $incident->status = IncidentStatus::Acknowledged;
        $incident->acknowledged_at = now();
        $incident->acknowledged_by = $request->user()->id;
        $incident->save();

        AuditLog::record('incident.acknowledged', $incident);

        return back()->with('status', 'Incident pris en charge.');
    }
}
