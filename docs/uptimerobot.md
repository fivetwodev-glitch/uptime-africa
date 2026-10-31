# Intégration UptimeRobot (API v3)

Source unique : spécification officielle OpenAPI — https://cdn.uptimerobot.com/api/openapi.yaml
(rendu : https://uptimerobot.com/api/v3/). Aucune réponse n'a été inventée : les champs lus sont ceux des schémas `UserDto`, `MonitorDto`, `MonitorPaginationDto`, `ApiErrorResponseDto`.

## Configuration (serveur uniquement)

| Variable | Rôle |
|---|---|
| `ZITE_UPTIMEROBOT_API_KEY` | Clé API du compte central. **Uniquement** dans `.env` du serveur. |
| `UPTIMEROBOT_SYNC_EVERY` | Minutes entre deux synchronisations (défaut 5, min 1). |
| `UPTIMEROBOT_RESPONSE_TIMES_PER_RUN` | Temps de réponse récupérés par passage (défaut 3, 0 = désactivé). |
| `UPTIME_FAILURES_BEFORE_INCIDENT` | Échecs consécutifs avant incident (défaut et minimum 2). |
| `UPTIME_SYNC_TRIGGER_TOKEN` | Jeton du déclencheur externe (vide = désactivé). |
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
| Lire un moniteur | `GET /monitors/{id}` | association par ID, synchronisation d'un site |
| Temps de réponse | `GET /monitors/{id}/stats/response-time?from=…&to=…&includeTimeSeries=true` | dernière valeur de `time_series`, budget limité. `to` est indiqué facultatif dans la spec mais l'API réelle répond 400 (`000-012`) sans lui. |
| Modifier | `PATCH /monitors/{id}` | nom, URL, intervalle, délai |
| Pause / reprise | `POST /monitors/{id}/pause` · `/start` | |
| Supprimer | `DELETE /monitors/{id}` | seulement les moniteurs créés par Uptime Africa |

Authentification : `Authorization: Bearer <clé>`.

## Moteur de synchronisation (`App\Services\UptimeRobot\SyncEngine`)

### Déclenchement (sans processus permanent)

| Mode | Mise en place | Remarque |
|---|---|---|
| Cron Hostinger (recommandé) | `* * * * * cd ~/uptime-africa && php artisan schedule:run >> /dev/null 2>&1` | `uptimerobot:sync` est appelé chaque minute ; le moteur n'interroge l'API que toutes les `UPTIMEROBOT_SYNC_EVERY` minutes. |
| Service externe (si pas de cron) | `POST https://<domaine>/declencheur/synchronisation` avec `Authorization: Bearer <UPTIME_SYNC_TRIGGER_TOKEN>` | Désactivé (404) sans jeton de 32+ caractères ; 4 appels/min max ; jeton invalide journalisé (canal security). |
| Manuel | Boutons « Synchroniser UptimeRobot » (liste des sites, agence), « Synchroniser » (fiche site), « Synchroniser maintenant » (super admin) | Ignore l'espacement, pas le verrou. |

### Fréquence et quotas

- `UPTIMEROBOT_SYNC_EVERY` (défaut 5, min 1). Le plan gratuit contrôle chaque moniteur toutes les 5 min : synchroniser plus souvent n'apporte rien.
- Coût d'un passage : `ceil(moniteurs / 200)` requêtes + au plus `UPTIMEROBOT_RESPONSE_TIMES_PER_RUN` (défaut 3) pour les temps de réponse, jamais si la limite restante (`X-RateLimit-Remaining`) descend à la réserve de 2.
- Plan gratuit (10 req/min) : 1 + 3 = 4 requêtes toutes les 5 min.

### Données enregistrées (table `checks`, une ligne par site et par passage)

| Donnée | Colonne |
|---|---|
| Site, agence | `site_id`, `agency_id` |
| Date et heure (minute du passage) | `checked_at` |
| Statut | `status` (up, down, degraded, paused, unknown) + `is_up` (true / false / null) |
| Code HTTP | `http_status` : `lastIncident.cause` pendant une panne, si compris entre 100 et 599 |
| Temps de réponse | `response_time_ms` : dernière valeur de `GET /monitors/{id}/stats/response-time` (si budget) |
| Fournisseur | `provider` = uptime_robot |
| Résultat de la synchronisation | `sync_result` (ok, api_error, monitor_missing) + `error_message` |

### Statuts

| UptimeRobot | Vérification | Effet |
|---|---|---|
| `UP` | up, is_up = true | compteur d'échecs à 0, incident ouvert résolu |
| `DOWN` | down, is_up = false | compteur +1 ; incident au 2e échec consécutif |
| `LOOKS_DOWN` | degraded, is_up = null | aucun (panne non confirmée par UptimeRobot) |
| `PAUSED` | paused, is_up = null | aucun ; alerte si la surveillance est active ici |
| `STARTED`, valeur inconnue | unknown, is_up = null | aucun |
| API injoignable / erreur | unknown, `sync_result = api_error` | aucun : **jamais une panne** ; statut du site inchangé |
| Moniteur supprimé | unknown, `sync_result = monitor_missing` | synchronisation du site en échec |

Les vérifications « inconnues » ne cassent ni ne complètent une série d'échecs et sont exclues des calculs de disponibilité.

### Incidents

- Ouverture après `UPTIME_FAILURES_BEFORE_INCIDENT` (défaut et minimum 2) échecs consécutifs confirmés ; avant, le site est « Dégradé ».
- Un seul incident ouvert par site (contrainte unique en base + verrou de ligne).
- Début = première vérification en échec depuis le dernier succès ; fin = première vérification UP ; durée calculée (`duration_seconds`).
- Historique conservé (incidents résolus et vérifications).

### Fiabilité

| Risque | Parade |
|---|---|
| Passages simultanés | verrou `Cache::lock` (10 min) + `withoutOverlapping` |
| Passage rejoué | unicité (site, fournisseur, minute) : `insertOrIgnore`, compteur non modifié |
| Lenteur API | délai 15 s, connexion 5 s |
| Erreurs | 3 essais (réseau, 5xx), 429 selon `Retry-After` ≤ 20 s ; un site en erreur n'arrête pas les autres |
| Journalisation | début/fin de passage, erreurs API (sans clé), jetons invalides |

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
