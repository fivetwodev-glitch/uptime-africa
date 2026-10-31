<?php

namespace App\Console\Commands\Notifications;

use App\Models\Incident;
use App\Services\Notifications\NotificationDispatcher;
use Illuminate\Console\Command;

class SendProlongedAlerts extends Command
{
    protected $signature = 'notifications:prolonged';

    protected $description = 'Alerte « incident prolongé » (une fois par incident et destinataire)';

    public function handle(NotificationDispatcher $dispatcher): int
    {
        $min = min(config('uptime.notifications.prolonged_choices'));
        $queued = 0;

        Incident::withoutGlobalScopes()->whereNull('resolved_at')
            ->where('started_at', '<=', now()->subMinutes($min))
            ->chunkById(100, function ($incidents) use ($dispatcher, &$queued) {
                foreach ($incidents as $incident) {
                    $queued += $dispatcher->incidentProlonged($incident);
                }
            });

        $this->line("{$queued} alerte(s) d'incident prolongé en file.");

        return self::SUCCESS;
    }
}
