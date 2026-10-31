<?php

namespace App\Services\UptimeRobot;

use App\Enums\IncidentStatus;
use App\Enums\IncidentType;
use App\Enums\MonitoringProvider;
use App\Enums\MonitoringStatus;
use App\Enums\SiteStatus;
use App\Enums\SyncStatus;
use App\Models\AuditLog;
use App\Models\Incident;
use App\Models\Site;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Lien entre les sites Uptime Africa et les moniteurs du compte UptimeRobot central.
 *
 * Doublons évités à trois niveaux :
 * 1. même URL dans une agence : contrainte unique (agency_id, url_hash) ;
 * 2. même moniteur rattaché à deux sites : contrainte unique (monitoring_provider, external_monitor_id) ;
 * 3. avant toute création, recherche d'un moniteur existant de même URL dans le compte central.
 */
class UptimeRobotMonitors
{
    public const LAST_SYNC_CACHE_KEY = 'uptimerobot:last_sync';

    /** Statuts documentés (GET /monitors, paramètre status) → statut local. */
    private const STATUS_MAP = [
        'UP' => SiteStatus::Up,
        'DOWN' => SiteStatus::Down,
        'LOOKS_DOWN' => SiteStatus::Degraded,
        'STARTED' => SiteStatus::Unknown,
    ];

    public function __construct(private readonly UptimeRobotClient $client) {}

    public function configured(): bool
    {
        return $this->client->isConfigured();
    }

    /**
     * Crée (ou réutilise) le moniteur d'un site enregistré avec le fournisseur UptimeRobot.
     * La synchronisation n'est marquée réussie que si l'API a confirmé la création.
     */
    public function provision(Site $site): void
    {
        $this->assertUptimeRobotSite($site);

        try {
            $existing = $this->findByUrl($site->url);

            if ($existing !== null) {
                $this->assertNotLinkedElsewhere((string) $existing['id'], $site);
                $this->link($site, $existing, managed: false);
                AuditLog::record('site.monitor_linked', $site, ['monitor_id' => $existing['id'], 'reason' => 'url_existante']);

                return;
            }

            $monitor = $this->client->createHttpMonitor(
                $this->friendlyName($site),
                $site->url,
                $site->interval_minutes * 60,
                $site->timeout_seconds,
                $site->expected_status_codes,
            );

            if (! isset($monitor['id']) || ! is_numeric($monitor['id'])) {
                throw new UptimeRobotException('Réponse de création UptimeRobot sans identifiant de moniteur.');
            }

            try {
                $this->link($site, $monitor, managed: true);
            } catch (Throwable $e) {
                // Compensation : pas de moniteur orphelin si l'enregistrement local échoue.
                rescue(fn () => $this->client->deleteMonitor((int) $monitor['id']), report: false);

                throw $e;
            }

            AuditLog::record('site.monitor_created', $site, ['monitor_id' => $monitor['id']]);
        } catch (UptimeRobotException $e) {
            $this->markFailed($site, $e->getMessage());

            throw $e;
        }
    }

    /** Associe un moniteur existant : il doit surveiller exactement l'URL du site et n'être lié à aucun autre site. */
    public function associate(Site $site, int $monitorId): void
    {
        $this->assertUptimeRobotSite($site);

        try {
            $monitor = $this->client->getMonitor($monitorId);

            if (! is_string($monitor['url'] ?? null) || Site::hashUrl($monitor['url']) !== Site::hashUrl($site->url)) {
                throw new UptimeRobotException('Ce moniteur ne surveille pas l\'adresse de ce site : association refusée.');
            }
            $this->assertNotLinkedElsewhere((string) $monitorId, $site);

            $this->link($site, $monitor, managed: false);
            AuditLog::record('site.monitor_linked', $site, ['monitor_id' => $monitorId]);
        } catch (UptimeRobotException $e) {
            $this->markFailed($site, $e->getMessage());

            throw $e;
        }
    }

    /** Répercute la pause / reprise locale sur le moniteur. */
    public function applyMonitoringStatus(Site $site): void
    {
        if (! $this->isLinked($site)) {
            return;
        }

        $id = (int) $site->external_monitor_id;
        $monitor = $site->monitoring_status === MonitoringStatus::Active
            ? $this->client->startMonitor($id)
            : $this->client->pauseMonitor($id);

        $this->apply($site, $monitor);
    }

    /** Répercute nom, URL, intervalle et délai sur un moniteur créé par Uptime Africa. */
    public function pushSettings(Site $site): void
    {
        if (! $this->isLinked($site) || ! $site->external_managed) {
            return;
        }

        try {
            $monitor = $this->client->updateMonitor((int) $site->external_monitor_id, [
                'friendlyName' => mb_substr($this->friendlyName($site), 0, 250),
                'url' => $site->url,
                'interval' => $site->interval_minutes * 60,
                'timeout' => $site->timeout_seconds,
            ]);
            $this->apply($site, $monitor);
        } catch (UptimeRobotException $e) {
            $this->markFailed($site, $e->getMessage());

            throw $e;
        }
    }

    /** Supprime à distance uniquement un moniteur créé par Uptime Africa. */
    public function deleteRemote(Site $site): void
    {
        if ($this->isLinked($site) && $site->external_managed) {
            try {
                $this->client->deleteMonitor((int) $site->external_monitor_id);
            } catch (UptimeRobotException $e) {
                if ($e->httpStatus !== 404) {
                    throw $e;
                }
            }
        }
    }

    /**
     * Récupère tous les moniteurs (pagination nextLink) et met à jour les sites liés.
     *
     * @return array{monitors:int, sites:int, updated:int, missing:int, at:string}
     */
    public function synchronize(): array
    {
        $monitors = [];
        foreach ($this->client->monitors() as $monitor) {
            if (isset($monitor['id'])) {
                $monitors[(string) $monitor['id']] = $monitor;
            }
        }

        $stats = ['monitors' => count($monitors), 'sites' => 0, 'updated' => 0, 'missing' => 0];

        Site::withoutGlobalScopes()
            ->where('monitoring_provider', MonitoringProvider::UptimeRobot)
            ->whereNotNull('external_monitor_id')
            ->chunkById(200, function ($sites) use ($monitors, &$stats) {
                foreach ($sites as $site) {
                    $stats['sites']++;
                    $monitor = $monitors[$site->external_monitor_id] ?? null;

                    if ($monitor === null) {
                        $stats['missing']++;
                        $this->markFailed($site, 'Moniteur introuvable dans le compte UptimeRobot (supprimé ?).');

                        continue;
                    }

                    $this->apply($site, $monitor);
                    $stats['updated']++;
                }
            });

        $stats['at'] = now()->toIso8601String();
        Cache::forever(self::LAST_SYNC_CACHE_KEY, $stats);
        Log::info('Synchronisation UptimeRobot terminée', $stats);

        return $stats;
    }

    /** Liste des moniteurs du compte avec le site local lié (super administrateur uniquement). */
    public function overview(): array
    {
        $linked = Site::withoutGlobalScopes()->with(['agency:id,name'])
            ->where('monitoring_provider', MonitoringProvider::UptimeRobot)->whereNotNull('external_monitor_id')
            ->get(['id', 'agency_id', 'name', 'external_monitor_id'])->keyBy('external_monitor_id');

        $rows = [];
        foreach ($this->client->monitors() as $monitor) {
            $rows[] = ['monitor' => $monitor, 'site' => $linked[(string) ($monitor['id'] ?? '')] ?? null];
        }

        return $rows;
    }

    /** Met à jour les données locales d'un site à partir d'un MonitorDto. */
    public function apply(Site $site, array $monitor): void
    {
        $remoteStatus = strtoupper((string) ($monitor['status'] ?? ''));
        $previous = $site->status;

        if (isset(self::STATUS_MAP[$remoteStatus])) {
            $site->status = self::STATUS_MAP[$remoteStatus];
        }

        $site->sync_status = SyncStatus::Synced;
        $site->sync_error = $remoteStatus === 'PAUSED' && $site->monitoring_status === MonitoringStatus::Active
            ? 'Le moniteur est en pause chez UptimeRobot alors que la surveillance est active ici.'
            : null;
        $site->last_synced_at = now();
        $site->saveQuietly();

        $this->trackIncident($site, $previous, $monitor);
    }

    private function trackIncident(Site $site, SiteStatus $previous, array $monitor): void
    {
        $open = Incident::withoutGlobalScopes()->where('site_id', $site->id)->whereNull('resolved_at')->first();

        if ($site->status === SiteStatus::Down && $open === null) {
            $last = is_array($monitor['lastIncident'] ?? null) ? $monitor['lastIncident'] : [];
            $startedAt = isset($last['startedAt']) && is_string($last['startedAt']) ? rescue(fn () => Carbon::parse($last['startedAt']), report: false) : null;

            $incident = new Incident([
                'site_id' => $site->id,
                'type' => IncidentType::Downtime,
                'status' => IncidentStatus::Open,
                'started_at' => $startedAt && $startedAt->isPast() ? $startedAt : now(),
                'description' => 'Panne signalée par UptimeRobot'.(is_string($last['reason'] ?? null) && $last['reason'] !== '' ? ' : '.$last['reason'] : '.'),
                'confirmed_failures' => 1,
            ]);
            $incident->agency_id = $site->agency_id;
            $incident->save();
        } elseif ($site->status === SiteStatus::Up && $open !== null && $previous !== SiteStatus::Up) {
            $open->status = IncidentStatus::Resolved;
            $open->resolve(now());
        }
    }

    private function link(Site $site, array $monitor, bool $managed): void
    {
        $site->external_monitor_id = (string) $monitor['id'];
        $site->external_managed = $managed;
        $site->save();
        $this->apply($site, $monitor);
    }

    private function markFailed(Site $site, string $message): void
    {
        $site->sync_status = SyncStatus::Failed;
        $site->sync_error = mb_substr($message, 0, 255);
        $site->saveQuietly();
    }

    private function findByUrl(string $url): ?array
    {
        $host = parse_url($url, PHP_URL_HOST) ?: $url;
        foreach ($this->client->monitors(['url' => $host]) as $monitor) {
            if (is_string($monitor['url'] ?? null) && Site::hashUrl($monitor['url']) === Site::hashUrl($url)) {
                return $monitor;
            }
        }

        return null;
    }

    private function assertNotLinkedElsewhere(string $monitorId, Site $site): void
    {
        $taken = Site::withoutGlobalScopes()
            ->where('monitoring_provider', MonitoringProvider::UptimeRobot)
            ->where('external_monitor_id', $monitorId)
            ->whereKeyNot($site->getKey())
            ->exists();

        if ($taken) {
            throw new UptimeRobotException('Cette adresse est déjà surveillée sur la plateforme par un autre site. Contactez le support si nécessaire.');
        }
    }

    private function assertUptimeRobotSite(Site $site): void
    {
        if ($site->monitoring_provider !== MonitoringProvider::UptimeRobot) {
            throw new UptimeRobotException('Ce site n\'utilise pas UptimeRobot.');
        }
    }

    private function isLinked(Site $site): bool
    {
        return $site->monitoring_provider === MonitoringProvider::UptimeRobot && is_numeric($site->external_monitor_id);
    }

    private function friendlyName(Site $site): string
    {
        // Le nom porte la référence du site, sans données personnelles du client.
        return $site->name.' · UA-'.$site->id;
    }
}
