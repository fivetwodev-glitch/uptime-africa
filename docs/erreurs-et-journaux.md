# Erreurs et journalisation

## Gestion centralisée des erreurs (`bootstrap/app.php`)

| Type | Affichage | Journal |
| --- | --- | --- |
| `UptimeException` (erreur métier) | Message utilisateur sûr + code HTTP choisi | `warning` avec contexte |
| `TenantViolationException` | 404 (on ne révèle pas l'existence de la ressource) | `warning` |
| Erreur HTTP (404, 403, 419, 429, 503…) | Pages françaises `resources/views/errors/` | selon Laravel |
| Erreur inattendue | Page 500 générique ; JSON sans détail si `APP_DEBUG=false` | `error` avec trace |
| Validation | Messages français (`lang/fr/validation.php`) | non journalisé |

Les réponses JSON ont toujours la forme `{"message": "...", "request_id": "..."}`.

Lever une erreur métier :

```php
throw new UptimeException(
    userMessage: 'Votre offre ne permet pas d\'ajouter un site supplémentaire.',
    status: 422,
    logMessage: 'Quota de sites atteint',
    context: ['agency_id' => $agency->id],
);
```

## Identifiant de requête

`AssignRequestId` attribue un UUID à chaque requête (ou reprend un `X-Request-Id` entrant valide).
Il est ajouté automatiquement à chaque ligne de log (via `Context`), renvoyé dans l'en-tête
`X-Request-Id` et affiché sur les pages d'erreur (« Référence ») pour retrouver le log.

## Canaux de log (`config/logging.php`)

| Canal | Fichier | Usage |
| --- | --- | --- |
| `daily` (défaut) | `storage/logs/laravel-AAAA-MM-JJ.log` | Application |
| `monitoring` | `storage/logs/monitoring-AAAA-MM-JJ.log` | Contrôles, incidents, alertes, heartbeat |
| `security` | `storage/logs/security-AAAA-MM-JJ.log` | Connexions, refus d'accès, URL bloquées (90 j) |

Usage : `Log::channel('monitoring')->info('Site hors ligne', ['site_id' => $site->id]);`

Journal d'audit en base : `AuditLog::record('site.deleted', $site)` (qui, quoi, IP, request_id).
