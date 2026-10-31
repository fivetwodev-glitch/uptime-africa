# Notifications (email et WhatsApp)

## Vue d'ensemble

| Événement | Déclencheur | Email | WhatsApp |
|---|---|---|---|
| Panne | Ouverture d'un incident (moteur de synchronisation) | oui | oui |
| Retour en ligne | Résolution, seulement si la panne a été annoncée sur ce canal | oui | oui |
| Incident prolongé | `notifications:prolonged` (toutes les 5 min), seuil par destinataire | oui | oui |
| Résumé périodique | `notifications:digest` (horaire), 07:00 heure de l'agence | oui | non |
| Test | Bouton « Tester » (administrateur d'agence) | oui | oui |

Pipeline : `NotificationDispatcher` écrit une ligne dans `notification_logs` (journal), puis `SendNotification` (file) l'envoie via `ChannelSender`.

## Paramètres d'agence

Page Notifications, carte « Paramètres de l'agence » :

- activation globale ;
- canaux autorisés (email, WhatsApp) ;
- résumé périodique : désactivé, quotidien, hebdomadaire (lundi).

Par destinataire (canal) : types d'alertes (panne, retour, prolongé), délai avant alerte, seuil « prolongé », portée (tous les sites ou un site ; le réglage par site l'emporte).

## Anti-doublon

- Index unique `(incident_id, notification_channel_id, event)` et `dedup_key` unique (résumés : `digest:{agence}:{canal}:{fréquence}:{date}`).
- Insertion `insertOrIgnore` : un rejeu ou deux synchronisations concurrentes ne créent qu'une ligne.
- L'envoi « réclame » la ligne (`queued` → `sending`) : un job en double ne renvoie rien.
- Une alerte de panne retardée dont l'incident est résolu entre-temps est marquée « ignorée ».

## Statuts du journal

| Statut | Sens |
|---|---|
| En file | en attente d'envoi |
| Envoi en cours | réclamée par un job |
| Envoyée | acceptée par le fournisseur (SMTP ou Meta) — pas encore une remise |
| Remise / Lue | confirmée par le webhook WhatsApp |
| Échec | erreur définitive ou 3 essais épuisés (message d'erreur conservé) |
| Ignorée | canal non configuré, incident déjà résolu, etc. |

Nouveaux essais (3 au plus, 2/4 min) : erreurs temporaires seulement (Meta : codes 4, 80007, 130429, 131000, 131056, HTTP 5xx ; email : erreur de transport). Une coupure réseau pendant un envoi WhatsApp n'est **pas** relancée : le message a pu partir, un renvoi risquerait un doublon.

## Email (SMTP Hostinger)

Variables `.env` du serveur (jamais dans le dépôt) :

```
MAIL_MAILER=smtp
MAIL_SCHEME=smtps
MAIL_HOST=smtp.hostinger.com
MAIL_PORT=465
MAIL_USERNAME=alertes@votre-domaine
MAIL_PASSWORD=…
MAIL_FROM_ADDRESS=alertes@votre-domaine
```

Modèles : `resources/views/mail/` (HTML à styles en ligne, bleu nuit `#0a1733`, bleu électrique `#2f6bff`, version texte). L'identifiant SMTP `Message-ID` est enregistré comme identifiant externe.

## WhatsApp (WhatsApp Business Platform — Cloud API de Meta)

Fournisseur officiel : Cloud API (`graph.facebook.com/{version}/{phone_number_id}/messages`). Aucun envoi n'est simulé : sans configuration, la ligne est enregistrée « ignorée » avec le motif, et le bouton de test affiche « WhatsApp n'est pas configuré sur le serveur ».

### 1. Compte Meta

1. Créer une app Meta (type Business) et ajouter le produit WhatsApp.
2. Enregistrer le numéro d'envoi ; noter le **Phone number ID**.
3. Créer un **utilisateur système** (Business Manager) avec la permission `whatsapp_business_messaging` et générer un jeton permanent.
4. Noter le **App secret** (Paramètres de l'app > Général).

### 2. Modèles à faire approuver (WhatsApp Manager)

Catégorie **Utility**, langue **French (fr)**. Les noms et l'ordre des variables doivent correspondre exactement.

| Nom | Corps proposé |
|---|---|
| `uptime_africa_panne` | `Alerte Uptime Africa : le site {{1}} ({{2}}) est hors ligne depuis {{3}}. Cause : {{4}}.` |
| `uptime_africa_retour` | `Uptime Africa : le site {{1}} ({{2}}) est de nouveau en ligne. Durée de la panne : {{3}}.` |
| `uptime_africa_panne_prolongee` | `Uptime Africa : la panne du site {{1}} dure depuis {{2}}. Vérifiez {{3}}.` |
| `uptime_africa_test` | `Message de test Uptime Africa pour l'agence {{1}}. Vos alertes WhatsApp sont opérationnelles.` |

Un modèle non approuvé renvoie l'erreur 132001, affichée telle quelle dans le journal.

### 3. Variables serveur

```
WHATSAPP_ENABLED=true
WHATSAPP_API_VERSION=v23.0
WHATSAPP_PHONE_NUMBER_ID=…
WHATSAPP_ACCESS_TOKEN=…
WHATSAPP_WEBHOOK_VERIFY_TOKEN=…   # chaîne aléatoire choisie par vous
WHATSAPP_APP_SECRET=…
```

Noms de modèles modifiables via `WHATSAPP_TEMPLATE_*`.

### 4. Webhook des statuts

Dans l'app Meta > WhatsApp > Configuration :

- URL de rappel : `https://votre-domaine/webhooks/whatsapp`
- Jeton de vérification : la valeur de `WHATSAPP_WEBHOOK_VERIFY_TOKEN`
- S'abonner au champ `messages`.

Chaque POST est vérifié par `X-Hub-Signature-256` (HMAC-SHA256 avec l'App secret) ; signature invalide → 403. Les statuts ne reculent jamais (un « sent » arrivé après « delivered » est ignoré).

### Erreurs fréquentes

| Code | Signification |
|---|---|
| 190 | jeton invalide ou expiré |
| 131026 | message non remis (numéro sans WhatsApp, etc.) |
| 131047 | hors fenêtre de 24 h : un modèle est obligatoire |
| 132000 | nombre de variables différent du modèle |
| 132001 | modèle inexistant ou non approuvé dans cette langue |
| 133010 | numéro d'envoi non enregistré |
| 130429 | limite de débit (nouvel essai automatique) |

## Sécurité

- Jetons et secrets uniquement dans le `.env` du serveur : jamais en base, jamais dans les vues, jamais dans les journaux.
- Le webhook est exclu du CSRF mais signé ; limité en débit.
- Bouton de test : administrateurs d'agence, 3 essais par minute.

## Planification (cron Hostinger)

Le cron `schedule:run` (chaque minute) lance aussi :

- `queue:work --stop-when-empty` (envoi des notifications) ;
- `notifications:prolonged` toutes les 5 minutes ;
- `notifications:digest` chaque heure.

## Tests

`tests/Feature/Notifications/NotificationsSimulatedTest.php` : scénarios **simulés** (`Mail::fake`, `Http::fake` avec les réponses documentées par Meta). Aucun message réel n'est envoyé par la suite de tests.
