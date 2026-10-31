<?php

namespace App\Services\Monitoring;

use App\Models\Check;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Statistiques calculées UNIQUEMENT à partir des contrôles enregistrés.
 * Aucun contrôle sur la période → null (affiché comme « pas encore de données »).
 * Le filtre par agence est appliqué automatiquement (scope global).
 */
class UptimeStats
{
    /**
     * @return array{total:int, up:int, uptime:?float, avg_ms:?int}
     */
    public function summary(Carbon $since, ?string $siteId = null, ?Carbon $until = null): array
    {
        $row = Check::query()
            ->when($siteId, fn ($q) => $q->where('site_id', $siteId))
            ->where('checked_at', '>=', $since)
            ->when($until, fn ($q) => $q->where('checked_at', '<', $until))
            // Les vérifications « inconnues » (API muette, pause) ne comptent ni pour ni contre la disponibilité.
            ->whereNotNull('is_up')
            ->selectRaw('count(*) as total, sum(case when is_up then 1 else 0 end) as up_count, avg(case when is_up then response_time_ms end) as avg_ms')
            ->toBase()
            ->first();

        $total = (int) $row->total;

        return [
            'total' => $total,
            'up' => (int) $row->up_count,
            'uptime' => $total > 0 ? round($row->up_count / $total * 100, 3) : null,
            'avg_ms' => $row->avg_ms !== null ? (int) round($row->avg_ms) : null,
        ];
    }

    /**
     * Disponibilité par jour sur N jours (null pour un jour sans contrôle).
     *
     * @return Collection<int, array{date:Carbon, uptime:?float, total:int}>
     */
    public function dailyBars(string $siteId, int $days = 30): Collection
    {
        $since = now()->startOfDay()->subDays($days - 1);

        $rows = Check::query()
            ->where('site_id', $siteId)
            ->where('checked_at', '>=', $since)
            ->whereNotNull('is_up')
            ->get(['checked_at', 'is_up'])
            ->groupBy(fn (Check $check) => $check->checked_at->toDateString());

        return collect(range(0, $days - 1))->map(function (int $offset) use ($since, $rows) {
            $date = $since->copy()->addDays($offset);
            $checks = $rows->get($date->toDateString(), collect());
            $total = $checks->count();

            return [
                'date' => $date,
                'total' => $total,
                'uptime' => $total > 0 ? round($checks->where('is_up', true)->count() / $total * 100, 2) : null,
            ];
        });
    }

    /**
     * Points (horodatage, ms) des contrôles réussis pour le graphique de temps de réponse.
     *
     * @return Collection<int, array{at:Carbon, ms:int}>
     */
    public function responseSeries(string $siteId, Carbon $since): Collection
    {
        return Check::query()
            ->where('site_id', $siteId)
            ->where('checked_at', '>=', $since)
            ->where('is_up', true)
            ->whereNotNull('response_time_ms')
            ->orderBy('checked_at')
            ->get(['checked_at', 'response_time_ms'])
            ->map(fn (Check $check) => ['at' => $check->checked_at, 'ms' => $check->response_time_ms]);
    }
}
