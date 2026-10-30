<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Prouve que le cron tourne : mémorise l'heure du dernier passage
 * et pingue un service externe qui alerte en cas de silence.
 */
class Heartbeat extends Command
{
    protected $signature = 'uptime:heartbeat';

    protected $description = 'Enregistre le passage du planificateur et pingue la sonde externe';

    public function handle(): int
    {
        Cache::forever(config('uptime.heartbeat.cache_key'), now()->toIso8601String());

        $url = config('uptime.heartbeat.url');

        if (! $url) {
            return self::SUCCESS;
        }

        try {
            Http::timeout(5)->retry(2, 500)->get($url);
        } catch (Throwable $e) {
            Log::channel('monitoring')->warning('Ping heartbeat externe en échec.', ['error' => $e->getMessage()]);

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
