# Intégration UptimeRobot (API v3)

Source unique : spécification officielle OpenAPI — https://cdn.uptimerobot.com/api/openapi.yaml
(rendu : https://uptimerobot.com/api/v3/). Aucune réponse n'a été inventée : les champs lus sont ceux des schémas `UserDto`, `MonitorDto`, `MonitorPaginationDto`, `ApiErrorResponseDto`.

## Configuration (serveur uniquement)

| Variable | Rôle |
|---|---|
| `ZITE_UPTIMEROBOT_API_KEY` | Clé API du compte central. **Uniquement** dans `.env` du serveur. |
| `UPTIMEROBOT_SYNC_EVERY` | Minutes entre deux synchronisations (défaut 5). |
| `UPTIMEROBOT_TIMEOUT`, `UPTIMEROBOT_MAX_ATTEMPTS`, `UPTIMEROBOT_MAX_WAIT` | Délai HTTP (15 s), essais (3), attente max sur 429 (20 s). |

Règles appliquées : jamais envoyée au navigateur, jamais en base, jamais journalisée (seuls méthode, chemin, statut, code et message sont journalisés), jamais dans le code. Clé absente → message « Intégration UptimeRobot non configurée » dans Paramètres (agence) et dans /admin/uptimerobot. Test automatisé : `test_key_never_reaches_logs_database_or_pages`.

Après modification de `.env` : `php artisan config:clear` (ou `config:cache` en production).

## Endpoints utilisés

| Besoin | Endpoint | Notes |
|---|---|---|
| Tester la connexion | `GET /user/me` | plan, `monitorsCount`, `monitorLimit` |
| Lister / synchroniser | `GET /monitors?limit=200` | pagination par `nextLink` (seul l'hôte `api.uptimerobot.com` en https est suivi) |
| Doublons | `GET /monitors?url=<hôte>` | filtre partiel, comparaison exacte côté serveur (`Site::hashUrl`) |
| Créer | `POST /monitors` | `CreateHttpMonitorDto` : `type=HTTP`, `friendlyName`, `url`, `interval` (s), `timeout`, `httpMethodType=GET`, `followRedirections`, `successHttpResponseCodes` |
| Lire un moniteur | `GET /monitors/{id}` | association par ID |
| Modifier | `PATCH /monitors/{id}` | nom, URL, intervalle, délai |
| Pause / reprise | `POST /monitors/{id}/pause` · `/start` | |
| Supprimer | `DELETE /monitors/{id}` | seulement les moniteurs créés par Uptime Africa |

Authentification : `Authorization: Bearer <clé>`.

## Statuts

| UptimeRobot | Uptime Africa |
|---|---|
| `UP` | En ligne (incident ouvert → résolu) |
| `DOWN` | Hors ligne (incident ouvert, raison de `lastIncident`) |
| `LOOKS_DOWN` | Dégradé |
| `STARTED` | Inconnu (premier contrôle en cours) |
| `PAUSED` | statut inchangé ; alerte si la surveillance est active ici |

Le temps de réponse n'est pas importé (endpoint par moniteur, trop coûteux en requêtes).

## Création d'un moniteur à l'ajout d'un site

1. URL validée (http/https publique, anti-SSRF).
2. Doublon local refusé (même URL dans l'agence).
3. Recherche dans le compte central : moniteur de même URL libre → associé ; déjà lié à un autre site → refus.
4. `POST /monitors`, identifiant lu dans la réponse 201.
5. Identifiant enregistré ; `sync_status = synced` **seulement** à ce moment. Si l'enregistrement local échoue, le moniteur créé est supprimé (compensation).
6. Échec API → site conservé en « échec », message explicite, bouton « Créer le moniteur UptimeRobot » sur la fiche.

Association par ID : refusée si le moniteur ne surveille pas la même URL, ou est déjà lié à un site (toutes agences).

## Limites de requêtes et erreurs

- Plan gratuit : 10 requêtes/min ; Pro : limite de moniteurs × 2 (max 5 000). En-têtes `X-RateLimit-*` et `Retry-After` lus.
- 429 : nouvel essai après `Retry-After` (ou `X-RateLimit-Reset`) si ≤ 20 s, sinon abandon.
- Réseau / 5xx : 3 essais, attente 1 s puis 2 s. **Pas de nouvel essai pour `POST /monitors`** (risque de doublon), sauf 429.
- Autres 4xx : aucun nouvel essai ; message en français + détail et `code` de l'API.
- Synchronisation : 1 requête par tranche de 200 moniteurs, `withoutOverlapping`.

## Écrans

- Fiche site : identifiant, état et date de synchronisation, erreur, bouton de synchronisation.
- Paramètres (agence) : état de l'intégration, compteurs synchronisés / en attente / en échec.
- `/admin/uptimerobot` (super administrateur) : clé configurée ou non, test de connexion, synchronisation manuelle, liste des moniteurs du compte avec site et agence liés.

## Tests réels (à lancer sur un poste ayant la clé)

Windows (PowerShell), dans le dossier du projet, après avoir ajouté la clé dans `.env` :

```powershell
php artisan config:clear
php artisan uptimerobot:test
php artisan uptimerobot:test-create https://www.exemple-reel.ci --delete
php artisan uptimerobot:sync
```

`test-create` refuse si l'URL est déjà surveillée, crée le moniteur, le relit par `GET /monitors/{id}` et affiche les champs retournés par l'API. `--delete` le supprime ensuite. Intervalle par défaut 300 s (`--interval=` pour un plan payant).

Hostinger : même commandes via SSH ; la synchronisation tourne via le cron unique `schedule:run`.
