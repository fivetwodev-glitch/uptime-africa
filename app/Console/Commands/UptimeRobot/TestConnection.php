<?php

namespace App\Console\Commands\UptimeRobot;

use App\Services\UptimeRobot\UptimeRobotClient;
use App\Services\UptimeRobot\UptimeRobotException;
use Illuminate\Console\Command;

/** Test réel : GET /user/me avec la clé du serveur. N'affiche jamais la clé. */
class TestConnection extends Command
{
    protected $signature = 'uptimerobot:test';

    protected $description = 'Teste la connexion à l\'API UptimeRobot v3 (GET /user/me)';

    public function handle(UptimeRobotClient $client): int
    {
        if (! $client->isConfigured()) {
            $this->error(UptimeRobotException::notConfigured()->getMessage());

            return self::FAILURE;
        }

        try {
            $me = $client->me();
        } catch (UptimeRobotException $e) {
            $this->error('Échec : '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info('Connexion à l\'API UptimeRobot v3 réussie.');
        $this->table(['Champ (réponse API)', 'Valeur'], [
            ['email', $me['email'] ?? '—'],
            ['activeSubscription.plan', $me['activeSubscription']['plan'] ?? '—'],
            ['activeSubscription.status', $me['activeSubscription']['status'] ?? '—'],
            ['monitorsCount', $me['monitorsCount'] ?? '—'],
            ['monitorLimit', $me['monitorLimit'] ?? '—'],
            ['X-RateLimit-Remaining', $client->rateLimitRemaining ?? '—'],
        ]);

        return self::SUCCESS;
    }
}
