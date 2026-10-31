<?php

namespace App\Console\Commands\UptimeRobot;

use App\Services\UptimeRobot\SyncEngine;
use App\Services\UptimeRobot\SyncReport;
use Illuminate\Console\Command;

class SyncMonitors extends Command
{
    protected $signature = 'uptimerobot:sync {--force : ignorer l\'espacement minimal entre deux passages}';

    protected $description = 'Récupère les statuts UptimeRobot, enregistre les vérifications et gère les incidents';

    public function handle(SyncEngine $engine): int
    {
        $report = $engine->run(force: (bool) $this->option('force'), trigger: 'cron');

        if ($report['status'] === 'skipped') {
            $this->line('Passage ignoré : '.match ($report['reason'] ?? '') {
                'not_due' => 'prochain passage pas encore dû (UPTIMEROBOT_SYNC_EVERY).',
                default => $report['message'] ?? $report['reason'] ?? '',
            });

            return ($report['reason'] ?? '') === 'not_configured' ? self::FAILURE : self::SUCCESS;
        }

        $this->line(SyncReport::summary($report));

        return $report['status'] === 'ok' ? self::SUCCESS : self::FAILURE;
    }
}
