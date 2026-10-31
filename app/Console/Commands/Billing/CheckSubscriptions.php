<?php

namespace App\Console\Commands\Billing;

use App\Services\Billing\SubscriptionManager;
use Illuminate\Console\Command;

class CheckSubscriptions extends Command
{
    protected $signature = 'subscriptions:check';

    protected $description = 'Fins d\'essai, échéances dépassées (paiement en retard) et expirations après le délai de grâce';

    public function handle(SubscriptionManager $manager): int
    {
        $counts = $manager->sweep();
        $this->info("Essais terminés : {$counts['trials_expired']} · en retard : {$counts['past_due']} · expirés : {$counts['expired']}");

        return self::SUCCESS;
    }
}
