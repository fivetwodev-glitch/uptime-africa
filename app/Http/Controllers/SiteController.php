<?php

namespace App\Http\Controllers;

use App\Enums\MonitoringProvider;
use App\Enums\MonitoringStatus;
use App\Enums\SiteStatus;
use App\Http\Requests\SiteRequest;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Site;
use App\Services\Billing\PlanLimits;
use App\Services\Monitoring\UptimeStats;
use App\Services\UptimeRobot\SyncEngine;
use App\Services\UptimeRobot\SyncReport;
use App\Services\UptimeRobot\UptimeRobotException;
use App\Services\UptimeRobot\UptimeRobotMonitors;
use App\Support\TenantContext;
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
            'hasUptimeRobotSites' => Site::where('monitoring_provider', MonitoringProvider::UptimeRobot)->whereNotNull('external_monitor_id')->exists(),
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

    public function store(SiteRequest $request, UptimeRobotMonitors $uptimeRobot): RedirectResponse
    {
        app(PlanLimits::class)->ensureCanAdd($request->user()->agency_id, 'sites', 'url');
        $data = $request->validated();
        $monitorId = $this->pullUptimeRobotId($data);

        $site = new Site($data);
        $site->next_check_at = now();
        $site->save();

        AuditLog::record('site.created', $site, ['url' => $site->url]);

        $redirect = redirect()->route('sites.show', $site);

        if ($site->monitoring_provider === MonitoringProvider::UptimeRobot) {
            return $this->linkUptimeRobot($redirect, $uptimeRobot, $site, $monitorId, 'Site ajouté.');
        }

        return $redirect->with('status', 'Site ajouté. Il sera contrôlé dès que le moteur de surveillance sera actif.');
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

    public function update(SiteRequest $request, Site $site, UptimeRobotMonitors $uptimeRobot): RedirectResponse
    {
        $data = $request->validated();
        $monitorId = $this->pullUptimeRobotId($data);
        $before = $site->replicate();
        $wasUptimeRobot = $site->monitoring_provider === MonitoringProvider::UptimeRobot && $site->external_monitor_id !== null;

        $site->fill($data);
        $isUptimeRobot = $site->monitoring_provider === MonitoringProvider::UptimeRobot;

        // Le lien est conservé si le moniteur reste le même et surveille toujours la bonne URL
        // (un moniteur créé par nous suit le changement d'URL via PATCH).
        $keepLink = $isUptimeRobot && $wasUptimeRobot
            && ($monitorId === null || $monitorId === (int) $before->external_monitor_id)
            && (! $site->isDirty('url') || $before->external_managed);

        if ($site->monitoring_provider === MonitoringProvider::Internal || ($isUptimeRobot && ! $keepLink)) {
            $site->external_monitor_id = null;
        }
        if (! $keepLink) {
            $site->external_managed = false;
        }

        $changes = array_keys($site->getDirty());
        $site->save();

        AuditLog::record('site.updated', $site, ['fields' => $changes]);

        $redirect = redirect()->route('sites.show', $site);
        $response = null;

        try {
            if ($isUptimeRobot && ! $keepLink) {
                $response = $this->linkUptimeRobot($redirect, $uptimeRobot, $site, $monitorId, 'Site mis à jour.');
            } elseif ($keepLink && array_intersect($changes, ['name', 'url', 'interval_minutes', 'timeout_seconds'])) {
                $uptimeRobot->pushSettings($site);
            }

            // Ancien moniteur créé par nous et plus utilisé : supprimé chez UptimeRobot.
            if ($wasUptimeRobot && ! $keepLink && $before->external_managed) {
                $uptimeRobot->deleteRemote($before);
            }
        } catch (UptimeRobotException $e) {
            return $redirect->with('error', 'Site mis à jour, mais UptimeRobot n\'a pas pu être mis à jour : '.$e->getMessage());
        }

        if ($response) {
            return $response;
        }

        return $redirect->with('status', 'Site mis à jour.');
    }

    /** Synchronisation manuelle d'un site : crée/associe le moniteur s'il manque, sinon relit son statut. */
    public function sync(Site $site, UptimeRobotMonitors $uptimeRobot, SyncEngine $engine): RedirectResponse
    {
        $this->authorize('sites.manage');
        abort_unless($site->monitoring_provider === MonitoringProvider::UptimeRobot, 404);

        if ($site->external_monitor_id === null) {
            return $this->linkUptimeRobot(back(), $uptimeRobot, $site, null, '');
        }

        $report = $engine->runForSite($site);

        return back()->with($report['status'] === 'ok' ? 'status' : 'error', SyncReport::summary($report));
    }

    /** Synchronisation manuelle de tous les sites UptimeRobot de l'agence. */
    public function syncAll(SyncEngine $engine, TenantContext $tenant): RedirectResponse
    {
        $this->authorize('sites.manage');

        $report = $engine->run($tenant->id(), force: true, trigger: 'manual');

        return back()->with($report['status'] === 'ok' ? 'status' : 'error', SyncReport::summary($report));
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

        try {
            app(UptimeRobotMonitors::class)->applyMonitoringStatus($site);
        } catch (UptimeRobotException $e) {
            return back()->with('error', 'Statut changé ici, mais pas chez UptimeRobot : '.$e->getMessage());
        }

        return back()->with('status', $site->monitoring_status === MonitoringStatus::Active ? 'Surveillance reprise.' : 'Surveillance mise en pause.');
    }

    public function destroy(Site $site): RedirectResponse
    {
        $this->authorize('sites.manage');

        // Le moniteur distant est supprimé d'abord : en cas d'échec, le site reste en place.
        try {
            app(UptimeRobotMonitors::class)->deleteRemote($site);
        } catch (UptimeRobotException $e) {
            return back()->with('error', 'Suppression annulée : le moniteur UptimeRobot n\'a pas pu être supprimé ('.$e->getMessage().').');
        }

        AuditLog::record('site.deleted', $site, ['name' => $site->name, 'url' => $site->url]);
        $site->delete();

        return redirect()->route('sites.index')->with('status', 'Site supprimé, ainsi que son historique de contrôles et d\'incidents.');
    }

    /** Retire l'identifiant UptimeRobot saisi : il n'est enregistré qu'après vérification par l'API. */
    private function pullUptimeRobotId(array &$data): ?int
    {
        if (($data['monitoring_provider'] ?? null) !== MonitoringProvider::UptimeRobot->value) {
            return null;
        }
        $id = $data['external_monitor_id'] ?? null;
        unset($data['external_monitor_id']);

        return $id === null ? null : (int) $id;
    }

    private function linkUptimeRobot(RedirectResponse $redirect, UptimeRobotMonitors $uptimeRobot, Site $site, ?int $monitorId, string $prefix): RedirectResponse
    {
        try {
            $monitorId === null ? $uptimeRobot->provision($site) : $uptimeRobot->associate($site, $monitorId);
        } catch (UptimeRobotException $e) {
            return $redirect->with('error', trim($prefix.' Le moniteur UptimeRobot n\'est pas synchronisé : '.$e->getMessage().' Vous pouvez relancer la synchronisation depuis la fiche du site.'));
        }

        $verb = $site->external_managed ? 'créé' : 'associé';

        return $redirect->with('status', trim($prefix." Moniteur UptimeRobot n° {$site->external_monitor_id} {$verb} et synchronisé."));
    }
}
