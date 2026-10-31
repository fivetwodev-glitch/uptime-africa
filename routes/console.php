<?php

use Illuminate\Support\Facades\Schedule;

/*
| Planification. En production (Hostinger), un seul cron par minute :
|   * * * * * cd /chemin/uptime-africa && php artisan schedule:run >> /dev/null 2>&1
| En local : php artisan schedule:work
*/

Schedule::command('uptime:heartbeat')->everyMinute()->withoutOverlapping();

// Pas de worker permanent sur mutualisé : la file est vidée à chaque minute.
if (config('uptime.queue.run_from_scheduler')) {
    Schedule::command('queue:work', [
        '--stop-when-empty',
        '--max-time='.config('uptime.queue.max_time_seconds'),
        '--tries=3',
    ])->everyMinute()->withoutOverlapping();
}

Schedule::command('queue:prune-failed --hours=168')->daily();

// UptimeRobot : statuts récupérés toutes les N minutes (1 requête par page de 200 moniteurs).
if (config('uptime.uptimerobot.api_key')) {
    Schedule::command('uptimerobot:sync')
        ->cron('*/'.max(1, (int) config('uptime.uptimerobot.sync_every_minutes')).' * * * *')
        ->withoutOverlapping(10);
}
