<?php

return [

    /*
    | Paramètres métier d'Uptime Africa. Toutes les valeurs proviennent du .env.
    */

    'currency' => env('UPTIME_CURRENCY', 'XOF'),

    'timezone_display' => env('UPTIME_DISPLAY_TIMEZONE', 'Africa/Abidjan'),

    // Adresse affichée pour les demandes de changement d'offre. Vide = non affichée.
    'support_email' => env('UPTIME_SUPPORT_EMAIL'),

    'monitoring' => [
        // Intervalle minimal autorisé entre deux contrôles d'un même site (minutes).
        'min_interval_minutes' => (int) env('MONITOR_MIN_INTERVAL_MINUTES', 5),
        // Délai maximal d'une requête de contrôle (secondes).
        'request_timeout_seconds' => (int) env('MONITOR_REQUEST_TIMEOUT', 10),
        // Nombre de requêtes HTTP simultanées par cycle.
        'concurrency' => (int) env('MONITOR_CONCURRENCY', 20),
        // Budget maximal d'un cycle pour ne jamais chevaucher le cron suivant (secondes).
        'cycle_budget_seconds' => (int) env('MONITOR_CYCLE_BUDGET', 50),
        // Échecs consécutifs avant ouverture d'un incident.
        'failures_before_incident' => (int) env('MONITOR_FAILURES_BEFORE_INCIDENT', 2),
        'max_redirects' => (int) env('MONITOR_MAX_REDIRECTS', 5),
        'user_agent' => env('MONITOR_USER_AGENT', 'UptimeAfricaBot/1.0'),
        // Durée de conservation des contrôles bruts (jours).
        'raw_checks_retention_days' => (int) env('MONITOR_RAW_RETENTION_DAYS', 14),
    ],

    'heartbeat' => [
        // URL d'un service externe pingué à chaque minute (ex. healthchecks.io). Vide = désactivé.
        'url' => env('HEARTBEAT_URL'),
        'cache_key' => 'uptime:heartbeat:last_run',
    ],

    'queue' => [
        // Sur Hostinger mutualisé, la file est vidée par le scheduler (pas de worker permanent).
        'run_from_scheduler' => (bool) env('QUEUE_RUN_FROM_SCHEDULER', false),
        'max_time_seconds' => (int) env('QUEUE_MAX_TIME', 50),
    ],

    'whatsapp' => [
        'enabled' => (bool) env('WHATSAPP_ENABLED', false),
        'api_version' => env('WHATSAPP_API_VERSION', 'v21.0'),
        'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
        'access_token' => env('WHATSAPP_ACCESS_TOKEN'),
    ],

    /*
    | UptimeRobot (API v3, compte central). La clé vient UNIQUEMENT de la variable
    | d'environnement du serveur : jamais en base, jamais dans le code, jamais côté navigateur.
    */
    'uptimerobot' => [
        'api_key' => env('ZITE_UPTIMEROBOT_API_KEY'),
        'base_url' => 'https://api.uptimerobot.com/v3',
        'timeout_seconds' => (int) env('UPTIMEROBOT_TIMEOUT', 15),
        // Nouvelles tentatives sur erreur réseau, 5xx ou 429.
        'max_attempts' => (int) env('UPTIMEROBOT_MAX_ATTEMPTS', 3),
        // Attente maximale acceptée sur un 429 (Retry-After), pour rester dans la minute du cron.
        'max_wait_seconds' => (int) env('UPTIMEROBOT_MAX_WAIT', 20),
        // Taille de page de GET /monitors (doc : 1 à 200).
        'page_size' => 200,
        // Fréquence de synchronisation des statuts (minutes).
        // Plancher 1 min ; UptimeRobot ne contrôle pas plus souvent que l'intervalle du moniteur (5 min en plan gratuit).
        'sync_every_minutes' => max(1, (int) env('UPTIMEROBOT_SYNC_EVERY', 5)),
        // Règle d'incident : jamais moins de 2 échecs consécutifs confirmés.
        'failures_before_incident' => max(2, (int) env('UPTIME_FAILURES_BEFORE_INCIDENT', 2)),
        // Temps de réponse : 1 requête par site, plafonné par passage et par la limite restante.
        'response_times_per_run' => max(0, (int) env('UPTIMEROBOT_RESPONSE_TIMES_PER_RUN', 3)),
        'keep_requests_in_reserve' => 2,
        'lock_seconds' => 600,
        // Déclenchement externe (si le cron Hostinger n'est pas disponible) : jeton d'au moins 32 caractères.
        'trigger_token' => env('UPTIME_SYNC_TRIGGER_TOKEN'),
    ],

    'super_admin' => [
        'email' => env('SUPER_ADMIN_EMAIL'),
        'name' => env('SUPER_ADMIN_NAME', 'Administrateur'),
    ],

];
