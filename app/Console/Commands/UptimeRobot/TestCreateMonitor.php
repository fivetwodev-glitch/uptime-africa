<?php

namespace App\Console\Commands\UptimeRobot;

use App\Models\Site;
use App\Rules\PublicHttpUrl;
use App\Services\UptimeRobot\UptimeRobotClient;
use App\Services\UptimeRobot\UptimeRobotException;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

/**
 * Test réel de création : POST /monitors, puis relecture GET /monitors/{id}.
 * Refuse si un moniteur surveille déjà cette URL. --delete supprime le moniteur de test.
 */
class TestCreateMonitor extends Command
{
    protected $signature = 'uptimerobot:test-create {url : URL publique http(s) à surveiller} {--interval=300 : intervalle en secondes (min. 30, selon le plan)} {--delete : supprimer le moniteur après vérification}';

    protected $description = 'Crée réellement un moniteur HTTP chez UptimeRobot et le relit';

    public function handle(UptimeRobotClient $client): int
    {
        $url = trim((string) $this->argument('url'));
        $validator = Validator::make(['url' => $url], ['url' => ['required', 'max:2048', new PublicHttpUrl]]);
        if ($validator->fails()) {
            $this->error($validator->errors()->first('url'));

            return self::FAILURE;
        }

        try {
            foreach ($client->monitors(['url' => parse_url($url, PHP_URL_HOST)]) as $monitor) {
                if (is_string($monitor['url'] ?? null) && Site::hashUrl($monitor['url']) === Site::hashUrl($url)) {
                    $this->warn("Doublon évité : le moniteur n° {$monitor['id']} surveille déjà cette URL. Aucune création.");

                    return self::FAILURE;
                }
            }

            $created = $client->createHttpMonitor('Uptime Africa · test '.now()->format('Y-m-d H:i'), $url, max(30, (int) $this->option('interval')), 10);
            $id = (int) ($created['id'] ?? 0);
            if ($id === 0) {
                $this->error('Réponse de création sans identifiant : création non confirmée.');

                return self::FAILURE;
            }

            $read = $client->getMonitor($id);
        } catch (UptimeRobotException $e) {
            $this->error('Échec : '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info("Moniteur créé (POST /monitors) puis relu (GET /monitors/{$id}).");
        $this->table(['Champ (réponse API)', 'Valeur'], [
            ['id', $read['id'] ?? '—'],
            ['friendlyName', $read['friendlyName'] ?? '—'],
            ['url', $read['url'] ?? '—'],
            ['type', $read['type'] ?? '—'],
            ['interval', $read['interval'] ?? '—'],
            ['status', $read['status'] ?? '—'],
        ]);

        if ($this->option('delete')) {
            try {
                $client->deleteMonitor($id);
                $this->info("Moniteur de test n° {$id} supprimé (DELETE /monitors/{$id}).");
            } catch (UptimeRobotException $e) {
                $this->warn("Suppression impossible ({$e->getMessage()}) : supprimez le moniteur n° {$id} dans UptimeRobot.");
            }
        }

        return self::SUCCESS;
    }
}
