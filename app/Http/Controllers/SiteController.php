<?php

namespace App\Http\Controllers;

use App\Enums\MonitoringProvider;
use App\Enums\MonitoringStatus;
use App\Enums\SiteStatus;
use App\Http\Requests\SiteRequest;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Site;
use App\Services\Monitoring\UptimeStats;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SiteController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'statut' => ['nullable', Rule::enum(SiteStatus::class)],
            'surveillance' => ['nullable', Rule::enum(MonitoringStatus::class)],
            'client' => ['nullable', 'string', 'max:26'],
        ]);

        $sites = Site::query()
            ->with('client:id,name')
            ->when($filters['q'] ?? null, fn ($q, $term) => $q->where(fn ($q) => $q
                ->where('name', 'like', '%'.$term.'%')
                ->orWhere('url', 'like', '%'.$term.'%')))
            ->when($filters['statut'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['surveillance'] ?? null, fn ($q, $status) => $q->where('monitoring_status', $status))
            ->when($filters['client'] ?? null, fn ($q, $client) => $q->where('client_id', $client))
            ->orderByRaw("case status when 'down' then 0 when 'degraded' then 1 when 'unknown' then 2 else 3 end")
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('sites.index', [
            'sites' => $sites,
            'filters' => $filters,
            'clients' => Client::orderBy('name')->get(['id', 'name']),
            'hasAnySite' => $sites->total() > 0 || Site::exists(),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('sites.manage');

        return view('sites.form', [
            'site' => new Site(['client_id' => $request->query('client'), 'interval_minutes' => max(5, config('uptime.monitoring.min_interval_minutes')), 'timeout_seconds' => 10]),
            'clients' => Client::orderBy('name')->get(['id', 'name']),
            'intervals' => (new SiteRequest)->allowedIntervals(),
        ]);
    }

    public function store(SiteRequest $request): RedirectResponse
    {
        $site = new Site($request->validated());
        $site->next_check_at = now();
        $site->save();

        AuditLog::record('site.created', $site, ['url' => $site->url]);

        return redirect()->route('sites.show', $site)->with('status', 'Site ajouté. Il sera contrôlé dès que le moteur de surveillance sera actif.');
    }

    public function show(Site $site, UptimeStats $stats): View
    {
        $site->load('client:id,name');

        return view('sites.show', [
            'site' => $site,
            'uptime24h' => $stats->summary(now()->subDay(), $site->id),
            'uptime7d' => $stats->summary(now()->subDays(7), $site->id),
            'uptime30d' => $stats->summary(now()->subDays(30), $site->id),
            'bars' => $stats->dailyBars($site->id, 30),
            'series' => $stats->responseSeries($site->id, now()->subDay()),
            'incidents' => $site->incidents()->latest('started_at')->limit(10)->get(),
            'checks' => $site->checks()->latest('checked_at')->limit(15)->get(),
        ]);
    }

    public function edit(Site $site): View
    {
        $this->authorize('sites.manage');

        return view('sites.form', [
            'site' => $site,
            'clients' => Client::orderBy('name')->get(['id', 'name']),
            'intervals' => (new SiteRequest)->allowedIntervals(),
        ]);
    }

    public function update(SiteRequest $request, Site $site): RedirectResponse
    {
        $site->fill($request->validated());

        if ($site->monitoring_provider === MonitoringProvider::Internal) {
            $site->external_monitor_id = null;
        }

        $changes = array_keys($site->getDirty());
        $site->save();

        AuditLog::record('site.updated', $site, ['fields' => $changes]);

        return redirect()->route('sites.show', $site)->with('status', 'Site mis à jour.');
    }

    public function toggle(Site $site): RedirectResponse
    {
        $this->authorize('sites.manage');

        $site->monitoring_status = $site->monitoring_status === MonitoringStatus::Active
            ? MonitoringStatus::Paused
            : MonitoringStatus::Active;

        if ($site->monitoring_status === MonitoringStatus::Active) {
            $site->next_check_at = now();
        }

        $site->save();

        AuditLog::record($site->monitoring_status === MonitoringStatus::Active ? 'site.resumed' : 'site.paused', $site);

        return back()->with('status', $site->monitoring_status === MonitoringStatus::Active ? 'Surveillance reprise.' : 'Surveillance mise en pause.');
    }

    public function destroy(Site $site): RedirectResponse
    {
        $this->authorize('sites.manage');

        AuditLog::record('site.deleted', $site, ['name' => $site->name, 'url' => $site->url]);
        $site->delete();

        return redirect()->route('sites.index')->with('status', 'Site supprimé, ainsi que son historique de contrôles et d\'incidents.');
    }
}
