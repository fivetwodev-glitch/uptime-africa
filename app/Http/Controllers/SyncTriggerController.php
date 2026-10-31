<?php

namespace App\Http\Controllers;

use App\Services\UptimeRobot\SyncEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Point d'entrée pour un service de cron externe (si le cron Hostinger est indisponible).
 * Authentification : en-tête « Authorization: Bearer <UPTIME_SYNC_TRIGGER_TOKEN> ».
 * Le moteur décide lui-même si un passage est dû : appeler trop souvent ne surcharge pas l'API.
 */
class SyncTriggerController extends Controller
{
    public function __invoke(Request $request, SyncEngine $engine): JsonResponse
    {
        $token = (string) config('uptime.uptimerobot.trigger_token');

        if (strlen($token) < 32) {
            abort(404);
        }

        if (! hash_equals($token, (string) $request->bearerToken())) {
            Log::channel('security')->warning('Déclencheur de synchronisation : jeton invalide.', ['ip' => $request->ip()]);

            return response()->json(['status' => 'unauthorized'], 401);
        }

        $report = $engine->run(trigger: 'external');

        return response()->json(array_intersect_key($report, array_flip([
            'status', 'reason', 'at', 'sites', 'up', 'down', 'unknown', 'skipped', 'incidents_opened', 'incidents_resolved', 'duration_ms',
        ])), $report['status'] === 'failed' ? 503 : 200);
    }
}
