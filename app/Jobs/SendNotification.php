<?php

namespace App\Jobs;

use App\Models\NotificationLog;
use App\Services\Notifications\ChannelSender;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Envoi d'une ligne du journal. Les nouveaux essais sont gérés ici (3 au plus, espacés). */
class SendNotification implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public readonly int $logId) {}

    public function handle(ChannelSender $sender): void
    {
        $log = NotificationLog::withoutGlobalScopes()->find($this->logId);
        if ($log === null) {
            return;
        }

        if ($sender->send($log)) {
            self::dispatch($this->logId)->delay(now()->addMinutes(2 ** $log->fresh()->attempts));
        }
    }
}
