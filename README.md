# Uptime Africa

Plateforme SaaS de surveillance de sites web pour les agences web, freelances et entreprises
d'Afrique francophone. Interface en français, montants en FCFA (XOF).

> **Statut : phase 2 (interface + authentification).** Base de données, isolation multi-agences,
> authentification complète (inscription, connexion, mot de passe oublié, invitations, 3 rôles)
> et interface (8 pages) sont en place. Le moteur de contrôles
> et l'envoi des alertes arrivent à la phase suivante : d'ici là, les pages de mesures affichent
> des états vides explicites (voir [docs/interface.md](docs/interface.md)).

## Stack

| Couche | Choix | Version |
| --- | --- | --- |
| Langage | PHP | 8.3 |
| Framework | Laravel | 12.x |
| Base de données | SQLite (dév.) / MySQL-MariaDB (prod. Hostinger) | — |
| Frontend | Blade + Tailwind CSS (Vite) | Tailwind 4, Vite 7 |
| Tests | PHPUnit | 11.x |
| Style de code | Laravel Pint | 1.x |
| Node.js (build uniquement) | Node | 22 LTS (≥ 20.19) |

## Prérequis (Windows)

1. [Laravel Herd pour Windows](https://herd.laravel.com/windows) : fournit PHP 8.3 et Composer.
   Alternative : [Laragon](https://laragon.org).
2. [Node.js 22 LTS](https://nodejs.org).
3. [Git pour Windows](https://git-scm.com/download/win).

Vérification : `php -v` (≥ 8.3), `composer -V`, `node -v` (≥ 20.19).

## Installation et démarrage

```powershell
cd uptime-africa
powershell -ExecutionPolicy Bypass -File scripts\install.ps1
powershell -ExecutionPolicy Bypass -File scripts\start.ps1
```

Ouvrir <http://127.0.0.1:8000>, puis « Créer un compte » pour créer votre agence.
En local, les emails (réinitialisation, invitation) sont écrits dans `storage\logs\laravel-*.log`
(`MAIL_MAILER=log`) : copiez-y le lien.
Pour explorer avec des données de test : `php artisan db:seed --class=TestDataSeeder`
(les identifiants générés s'affichent dans le terminal).

`start.ps1` lance dans la même fenêtre : le serveur web, la file d'attente, le planificateur
(`schedule:work`) et Vite (rechargement à chaud). `Ctrl+C` arrête tout.

Linux / macOS / WSL : `./scripts/install.sh` puis `./scripts/start.sh`.

## Commandes utiles

| Action | Commande |
| --- | --- |
| Tests + style | `powershell -ExecutionPolicy Bypass -File scripts\test.ps1` |
| Tests seuls | `php artisan test` |
| Corriger le style | `.\vendor\bin\pint` |
| Réinitialiser la base locale | `php artisan migrate:fresh` |
| Créer l'administrateur général | renseigner `SUPER_ADMIN_EMAIL` dans `.env`, puis `php artisan db:seed` |
| Charger les données de test | `php artisan db:seed --class=TestDataSeeder` |
| Supprimer les données de test | `php artisan uptime:purge-test-data` |
| Lire les logs | `storage\logs\laravel-AAAA-MM-JJ.log` |
| Vérifier le planificateur | `php artisan schedule:list` |
| UptimeRobot : tester la connexion | `php artisan uptimerobot:test` |
| UptimeRobot : test réel de création | `php artisan uptimerobot:test-create https://votre-site.ci --delete` |
| UptimeRobot : synchroniser | `php artisan uptimerobot:sync` |

## Arborescence

```text
app/
  Console/Commands/     commandes artisan (uptime:heartbeat)
  Enums/                rôles, statuts de site, types d'erreur…
  Exceptions/           UptimeException (erreurs métier), TenantViolationException
  Http/Middleware/      AssignRequestId, SecurityHeaders, SetTenantContext
  Models/               Agency, User, Client, Site, Check, Incident, Alert, Plan…
    Concerns/           BelongsToAgency (isolation multi-agences)
    Scopes/             AgencyScope
  Providers/            configuration globale (sécurité, mots de passe, HTTPS)
  Services/             Monitoring, Notifications, Billing (phases 3 à 7)
  Support/              TenantContext, Money (FCFA)
bootstrap/app.php       middlewares + gestion centralisée des erreurs
config/uptime.php       paramètres métier (lus depuis .env)
database/               migrations, factories (tests uniquement), seeders
deploy/hostinger/       cron et script de déploiement (non exécutés)
docs/                   documentation technique
lang/fr/                traductions françaises
resources/views/        layouts, pages, pages d'erreur
routes/                 web.php, console.php (planification)
scripts/                installation, démarrage, tests (PowerShell + bash)
tests/                  Unit/ et Feature/
```

## Configuration

| Fichier | Usage |
| --- | --- |
| `.env.example` | Modèle de développement (SQLite, emails écrits dans les logs) |
| `.env.production.example` | Modèle de production Hostinger (MySQL, SMTP, HTTPS, debug désactivé) |

Aucune clé réelle n'est versionnée. Les secrets (WhatsApp, SMTP, base) restent côté serveur ;
le frontend ne lit que les variables `VITE_*`, qui ne doivent jamais contenir de secret.
Détail de chaque variable : [docs/configuration.md](docs/configuration.md).

## Documentation

- [Architecture](docs/architecture.md)
- [Base de données](docs/base-de-donnees.md)
- [Interface et routes](docs/interface.md)
- [Configuration](docs/configuration.md)
- [Erreurs et journalisation](docs/erreurs-et-journaux.md)
- [Sécurité, rôles et authentification](docs/securite.md)
- [Intégration UptimeRobot v3](docs/uptimerobot.md)
- [Notifications email et WhatsApp](docs/notifications.md)
- [Déploiement Hostinger](docs/deploiement-hostinger.md) (pas encore exécuté)

## Règles du projet

- Aucune donnée fictive en base hors des tests (les factories ne servent qu'aux tests).
- Toute table métier porte `agency_id` et utilise le trait `BelongsToAgency`.
- Tout changement passe par les tests (`scripts\test.ps1`) et la CI GitHub Actions.
