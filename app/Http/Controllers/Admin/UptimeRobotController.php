<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Services\UptimeRobot\SyncEngine;
use App\Services\UptimeRobot\SyncReport;
use App\Services\UptimeRobot\UptimeRobotClient;
use App\Services\UptimeRobot\UptimeRobotException;
use App\Services\UptimeRobot\UptimeRobotMonitors;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

/**
 * Compte UptimeRobot central : réservé au super administrateur,
 * car il contient les URL de toutes les agences.
 */
class UptimeRobotController extends Controller
{
    public function index(Request $request, UptimeRobotMonitors $monitors): View
    {
        $this->authorize('admin.access');

        $rows = null;
        $error = null;
        if ($monitors->configured() && $request->boolean('lister')) {
            try {
                $rows = $monitors->overview();
            } catch (UptimeRobotException $e) {
                $error = $e->getMessage();
            }
        }

        return view('admin.uptimerobot', [
            'configured' => $monitors->configured(),
            'rows' => $rows,
            'listError' => $error,
            'lastSync' => Cache::get(UptimeRobotMonitors::LAST_SYNC_CACHE_KEY),
            'account' => session('uptimerobot_account'),
        ]);
    }

    public function test(UptimeRobotClient $client): RedirectResponse
    {
        $this->authorize('admin.access');

        try {
            $me = $client->me();
        } catch (UptimeRobotException $e) {
            return back()->with('error', 'Test de connexion échoué : '.$e->getMessage());
        }

        AuditLog::record('uptimerobot.tested', null);

        return back()->with('status', 'Connexion à l\'API UptimeRobot réussie.')->with('uptimerobot_account', [
            'plan' => $me['activeSubscription']['plan'] ?? null,
            'monitorsCount' => $me['monitorsCount'] ?? null,
            'monitorLimit' => $me['monitorLimit'] ?? null,
        ]);
    }

    public function sync(SyncEngine $engine): RedirectResponse
    {
        $this->authorize('admin.access');

        $report = $engine->run(force: true, trigger: 'manual');

        return back()->with($report['status'] === 'ok' ? 'status' : 'error', SyncReport::summary($report));
    }
}
