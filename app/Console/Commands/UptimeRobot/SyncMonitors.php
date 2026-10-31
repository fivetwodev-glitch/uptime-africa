<?php

namespace App\Console\Commands\UptimeRobot;

use App\Services\UptimeRobot\UptimeRobotException;
use App\Services\UptimeRobot\UptimeRobotMonitors;
use Illuminate\Console\Command;

class SyncMonitors extends Command
{
    protected $signature = 'uptimerobot:sync';

    protected $description = 'Récupère les statuts des moniteurs UptimeRobot et met à jour les sites liés';

    public function handle(UptimeRobotMonitors $monitors): int
    {
        try {
            $stats = $monitors->synchronize();
        } catch (UptimeRobotException $e) {
            $this->error('Synchronisation interrompue : '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info("{$stats['monitors']} moniteur(s) lus, {$stats['updated']} site(s) mis à jour, {$stats['missing']} moniteur(s) introuvable(s).");

        return self::SUCCESS;
    }
}
