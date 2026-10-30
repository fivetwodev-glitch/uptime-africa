# Architecture

Monolithe Laravel unique : il sert l'interface web et exécute le moteur de surveillance
via un cron par minute. MySQL est la seule source de vérité et sert aussi de file d'attente.
Document de référence complet : « Uptime Africa — Architecture technique v1 ».

## Contraintes Hostinger mutualisé prises en compte

| Contrainte | Conséquence dans le code |
| --- | --- |
| Pas de processus permanent | `queue:work --stop-when-empty --max-time=50` lancé par le planificateur (`QUEUE_RUN_FROM_SCHEDULER=true`) |
| Cron (exemple officiel : chaque minute) | Un seul cron : `php artisan schedule:run` |
| PHP max 360 s (Premium/Business) | Cycle de contrôles borné à `MONITOR_CYCLE_BUDGET` (50 s) |
| Base MySQL 3 Go max | Contrôles bruts conservés `MONITOR_RAW_RETENTION_DAYS` jours + table `daily_stats` |
| Un seul point de contrôle | Incident ouvert après `MONITOR_FAILURES_BEFORE_INCIDENT` échecs consécutifs |
| Panne possible du serveur | Commande `uptime:heartbeat` qui pingue une sonde externe (`HEARTBEAT_URL`) |

## Modèle de données

```text
agencies ─┬─ users (agency_id null = administrateur général)
          ├─ subscriptions ── plans (prix en FCFA, quotas)
          ├─ notification_channels (email, whatsapp)
          ├─ audit_logs
          └─ clients ── sites ─┬─ checks (code HTTP, temps de réponse, erreur)
                               ├─ incidents ── alerts (envois par canal)
                               └─ daily_stats (uptime %, temps moyen)
```

- Identifiants ULID (non devinables) pour agences, clients, sites, incidents.
- Suppression d'une agence = suppression en cascade de toutes ses données.
- `checks.agency_id` est dupliqué volontairement pour filtrer sans jointure.

## Isolation multi-agences

1. `SetTenantContext` (middleware web) lit l'agence de l'utilisateur connecté.
2. `TenantContext` mémorise l'agence pour la requête.
3. `AgencyScope` ajoute `where agency_id = ?` à toutes les requêtes des modèles `BelongsToAgency`.
4. À l'écriture, `agency_id` est rempli d'office ; une autre agence lève `TenantViolationException` (404).
5. Sans contexte (planificateur, administrateur général), aucun filtre n'est appliqué.

Tests : `tests/Feature/TenantIsolationTest.php`.

## Plan de développement

| Phase | Contenu | Statut |
| --- | --- | --- |
| 0 | Preuve Hostinger (cron 1 min, curl sortant, SMTP) | À faire sur le serveur |
| 1 | Fondations : schéma, multi-agences, erreurs, logs, CI | **Fait (initialisation)** — reste : authentification |
| 2 | Clients & sites, validations, anti-SSRF, quotas | À faire |
| 3 | Moteur de contrôles concurrent | À faire |
| 4 | Incidents + alertes email | À faire |
| 5 | Tableau de bord, historique, agrégats, purge | À faire |
| 6 | WhatsApp (API Cloud Meta) | À faire |
| 7 | Abonnements FCFA | À faire |
| 8 | Administration, 2FA, durcissement | À faire |
