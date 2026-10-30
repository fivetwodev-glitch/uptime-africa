<?php

namespace App\Http\Controllers;

use App\Enums\SiteStatus;
use App\Models\Check;
use App\Models\Client;
use App\Models\Incident;
use App\Models\Site;
use App\Services\Monitoring\UptimeStats;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(UptimeStats $stats): View
    {
        $byStatus = Site::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return view('dashboard', [
            'totalSites' => (int) $byStatus->sum(),
            'upSites' => (int) $byStatus->get(SiteStatus::Up->value, 0),
            'downSites' => (int) $byStatus->get(SiteStatus::Down->value, 0),
            'degradedSites' => (int) $byStatus->get(SiteStatus::Degraded->value, 0),
            'unknownSites' => (int) $byStatus->get(SiteStatus::Unknown->value, 0),
            'clientsCount' => Client::count(),
            'last24h' => $stats->summary(now()->subDay()),
            'openIncidentsCount' => Incident::open()->count(),
            'openIncidents' => Incident::open()->with('site:id,name,url')->latest('started_at')->limit(5)->get(),
            'latestIncidents' => Incident::with('site:id,name')->latest('started_at')->limit(5)->get(),
            'latestChecks' => Check::with('site:id,name')->latest('checked_at')->limit(8)->get(),
        ]);
    }
}
