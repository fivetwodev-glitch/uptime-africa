# Configuration

Toute la configuration passe par les variables d'environnement (`.env`), lues dans
`config/*.php`. Ne jamais appeler `env()` ailleurs que dans `config/` (sinon les valeurs
disparaissent après `php artisan config:cache` en production).

## Variables spécifiques à Uptime Africa (`config/uptime.php`)

| Variable | Défaut | Rôle |
| --- | --- | --- |
| `UPTIME_CURRENCY` | `XOF` | Devise des abonnements |
| `UPTIME_DISPLAY_TIMEZONE` | `Africa/Abidjan` | Fuseau d'affichage (stockage en UTC) |
| `MONITOR_MIN_INTERVAL_MINUTES` | `5` | Intervalle minimal entre deux contrôles d'un site |
| `MONITOR_REQUEST_TIMEOUT` | `10` | Délai max d'une requête de contrôle (s) |
| `MONITOR_CONCURRENCY` | `20` | Requêtes simultanées par cycle |
| `MONITOR_CYCLE_BUDGET` | `50` | Durée max d'un cycle (s), < 60 |
| `MONITOR_FAILURES_BEFORE_INCIDENT` | `2` | Échecs consécutifs avant incident |
| `MONITOR_MAX_REDIRECTS` | `5` | Redirections suivies |
| `MONITOR_USER_AGENT` | `UptimeAfricaBot/1.0` | User-Agent des contrôles |
| `MONITOR_RAW_RETENTION_DAYS` | `14` | Conservation des contrôles bruts |
| `HEARTBEAT_URL` | vide | URL de la sonde externe pinguée chaque minute |
| `QUEUE_RUN_FROM_SCHEDULER` | `false` | `true` sur Hostinger (pas de worker permanent) |
| `QUEUE_MAX_TIME` | `50` | Durée max de vidage de la file par minute (s) |
| `WHATSAPP_ENABLED` | `false` | Active l'envoi WhatsApp (phase 6) |
| `WHATSAPP_PHONE_NUMBER_ID` / `WHATSAPP_ACCESS_TOKEN` | vide | Identifiants Meta, serveur uniquement |
| `SUPER_ADMIN_EMAIL` / `SUPER_ADMIN_NAME` | vide | Administrateur général créé par `db:seed` |

## Développement vs production

| Paramètre | Développement | Production |
| --- | --- | --- |
| `APP_ENV` / `APP_DEBUG` | `local` / `true` | `production` / `false` |
| Base | SQLite (`database/database.sqlite`) | MySQL Hostinger |
| Emails | `log` (écrits dans les logs) | `smtp` (boîte Hostinger) |
| Logs | `daily`, niveau `debug` | `daily`, niveau `warning`, 30 jours |
| Sessions | cookie non sécurisé | `SESSION_SECURE_COOKIE=true`, `SESSION_ENCRYPT=true` |
| File d'attente | `queue:listen` (start.ps1) | vidée par le cron |
| HTTPS | non | forcé + en-tête HSTS |
| Cache de config | non | `config:cache`, `route:cache`, `view:cache` |
