<?php

use App\Services\Billing\Gateways\ManualGateway;

return [

    // Essai offert à l'inscription (code d'offre, durée). 0 jour = pas d'essai.
    'trial_plan' => env('BILLING_TRIAL_PLAN', 'pro'),
    'trial_days' => (int) env('BILLING_TRIAL_DAYS', 14),

    // Après l'échéance : « paiement en retard » pendant N jours, puis « expiré ».
    'grace_days' => (int) env('BILLING_GRACE_DAYS', 7),

    // Prestataires de paiement. Ajouter CinetPay / PayDunya ici une fois leur API
    // officielle vérifiée et la classe écrite (voir docs/abonnements.md).
    'default_gateway' => env('BILLING_GATEWAY', 'manual'),
    'gateways' => [
        'manual' => ManualGateway::class,
    ],

];
