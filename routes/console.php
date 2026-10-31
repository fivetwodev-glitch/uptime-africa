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

// UptimeRobot : le cron tourne chaque minute, le moteur n'interroge l'API que toutes les
// UPTIMEROBOT_SYNC_EVERY minutes (espacement mesuré, verrou contre les passages simultanés).
if (config('uptime.uptimerobot.api_key')) {
    Schedule::command('uptimerobot:sync')->everyMinute()->withoutOverlapping(10);
}

// Notifications : incidents prolongés (toutes les 5 min) et résumés périodiques (chaque heure, envoi à 7 h locale).
Schedule::command('notifications:prolonged')->everyFiveMinutes()->withoutOverlapping(10);
Schedule::command('notifications:digest')->hourlyAt(2)->withoutOverlapping(30);

// Abonnements : fins d'essai, échéances et expirations.
Schedule::command('subscriptions:check')->hourlyAt(5)->withoutOverlapping(30);
