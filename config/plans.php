<?php

/*
| Offres de départ. Insérées une seule fois (PlanCatalog::sync, appelé par la
| migration et le seeder) : ensuite, l'espace Super Admin fait foi et ces valeurs
| ne réécrasent jamais une offre modifiée.
|
| TARIFS NON VALIDÉS : les prix valent 0 et `price_validated` est faux. Tant qu'un
| tarif n'est pas validé dans l'administration, l'offre affiche « Tarif à valider »
| et ne peut pas être souscrite en payant (seulement en essai).
|
| Limites : null = illimité.
*/

return [

    'starter' => [
        'name' => 'Starter',
        'audience' => 'Pour les indépendants',
        'tagline' => 'Surveillance standard de quelques sites.',
        'max_sites' => 5,
        'max_clients' => 3,
        'max_users' => 1,
        'min_interval_minutes' => 5,
        'retention_days' => 30,
        'includes_whatsapp' => false,
        'whatsapp_monthly_quota' => 0,
        'features' => [],
        'sort_order' => 1,
    ],

    'pro' => [
        'name' => 'Pro',
        'audience' => 'Pour les agences web',
        'tagline' => 'Plus de sites, plusieurs clients, alertes avancées.',
        'max_sites' => 25,
        'max_clients' => null,
        'max_users' => 3,
        'min_interval_minutes' => 5,
        'retention_days' => 90,
        'includes_whatsapp' => true,
        'whatsapp_monthly_quota' => 300,
        'features' => ['multi_clients', 'advanced_notifications'],
        'sort_order' => 2,
    ],

    'business' => [
        'name' => 'Business',
        'audience' => 'Pour les grandes agences',
        'tagline' => 'Limites supérieures, équipes, rapports et priorité.',
        'max_sites' => 100,
        'max_clients' => null,
        'max_users' => 15,
        'min_interval_minutes' => 1,
        'retention_days' => 365,
        'includes_whatsapp' => true,
        'whatsapp_monthly_quota' => 1500,
        'features' => ['multi_clients', 'advanced_notifications', 'team_management', 'advanced_reports', 'priority_support'],
        'sort_order' => 3,
    ],

];
