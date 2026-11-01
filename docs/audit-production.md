# Audit avant mise en production — 2026-11-01

Environnement de recette proche de la production : `APP_ENV=production`, `APP_DEBUG=false`, HTTPS, MariaDB, sessions chiffrées en base, file d'envoi vidée par le cron simulé (`schedule:run` chaque minute), **vraie API UptimeRobot** (compte central, plan FREE). Emails : transport `log` (pas de SMTP réel disponible ici).

**Verdict : prêt pour un déploiement sur Hostinger Premium ou supérieur**, sous réserve des 4 points « à faire par vous » (section 5). Rien n'a été déployé.

## 1. Tests fonctionnels (réels, navigateur + API UptimeRobot)

| Fonction | Résultat | Preuve |
|---|---|---|
| Inscription | OK | agence créée, essai Pro 14 jours |
| Connexion | OK | mauvais mot de passe refusé ; déconnexion → `/connexion` |
| Création d'agence | OK | via l'inscription |
| Création de client | OK | |
| Ajout de site | OK | 2 sites |
| Création du moniteur UptimeRobot | OK | moniteurs réels 804501093 et 804501094 créés automatiquement |
| Synchronisation des statuts | OK | site sain « up », site 404 « down » avec code HTTP et temps de réponse |
| Création d'incident | OK | incident ouvert après 2 contrôles en échec confirmés |
| Résolution d'incident | OK | résolu automatiquement, durée 840 s |
| Notifications | OK (email) | email de test, alerte de panne, « Retour en ligne » envoyés par la file ; WhatsApp tracé « ignoré » (non configuré) |
| Abonnements | OK | tarif Pro validé par le Super Admin, demande Pro mensuel, paiement confirmé (référence OM), abonnement actif, historique visible |
| Permissions | OK | admin d'agence : 403 sur `/admin` ; collaborateur : 403 sur équipe, renouvellement, canaux, paramètres ; lecture des sites autorisée |
| Suppression de site | OK | moniteurs supprimés chez UptimeRobot (404 ensuite) |

Tests automatisés : **178 réussis (SQLite), 179 réussis (MariaDB)**, Pint OK, build OK.

## 2. Défauts trouvés pendant l'audit et corrigés

| Défaut | Gravité | Correction | Test |
|---|---|---|---|
| Modifier un site envoyait `timeout` en texte → UptimeRobot 400 | élevée | cast entier sur `interval_minutes` / `timeout_seconds` | reproduit puis corrigé |
| Après cet échec, l'URL modifiée n'était jamais répercutée ; le site affichait le statut de l'ancienne URL | élevée | détection « URL différente chez UptimeRobot » à chaque synchronisation + nouvel envoi à l'enregistrement d'un site désynchronisé | 2 tests + vérifié sur l'API réelle |
| Avec une synchronisation chaque minute, 2 lectures du même contrôle UptimeRobot ouvraient un incident trop tôt | moyenne | la panne doit couvrir au moins un intervalle du moniteur | 1 test |
| Fournisseurs « interne / BetterStack / autre » sélectionnables alors qu'ils ne surveillent rien | moyenne | seul UptimeRobot est proposé | tests mis à jour |
| Route `storage/{path}` exposée | faible | désactivée | 1 test |
| En-tête `X-Powered-By` (version PHP) | faible | supprimé | 1 test |

## 3. Sécurité

| Contrôle | Résultat |
|---|---|
| Isolation entre agences | OK : sites, clients, incidents d'une autre agence → 404 (lecture, modification, suppression, synchronisation) ; listes sans fuite |
| Protection des routes | OK : seules les pages publiques, invités et limitées en débit sont sans authentification ; `/admin` réservé au super admin |
| Clé API UptimeRobot | OK : variable serveur `ZITE_UPTIMEROBOT_API_KEY` uniquement ; absente des pages, de la base, des journaux, des assets, du dépôt |
| Formulaires | OK : CSRF (419 sans jeton), validation serveur, limites de débit sur connexion/inscription |
| Sessions | OK : en base, chiffrées, cookies `Secure`, `HttpOnly`, `SameSite=Lax`, révocables |
| Validation des entrées | OK : URL publiques uniquement, codes HTTP bornés, intervalles selon l'offre |
| Secrets dans le frontend | aucun (assets compilés inspectés) |
| Secrets dans Git | aucun (fichiers suivis + historique) ; `.env` ignoré |
| Endpoint de synchronisation externe | OK : 401 sans jeton ou jeton faux (journalisé) ; webhook WhatsApp non signé → 403 |
| En-têtes | CSP, HSTS, nosniff, `X-Frame-Options: DENY`, Referrer-Policy, Permissions-Policy |
| Erreurs | aucune trace technique affichée ; `/.env` et `/storage/logs` → 404 |
| Dépendances | `composer audit` et `npm audit` : 0 vulnérabilité |

Risque résiduel faible : le seeder affiche une fois le mot de passe provisoire du super admin dans le terminal (à changer à la 1re connexion).

## 4. Hostinger

| Forfait | Verdict |
|---|---|
| Single | **incompatible** (pas de Composer/SSH, 2 tâches cron max) |
| Premium | compatible |
| Business / Unlimited, Cloud | compatibles, recommandés pour la croissance |
| VPS | alternative si besoin d'un worker permanent ou d'intervalles de 1 min à grande échelle |

Détails, commandes, variables, sauvegardes, maintenance et dépannage : `docs/deploiement-hostinger.md`.
Outils ajoutés : `php artisan uptime:preflight --network` (go/no-go, n'affiche aucun secret), `deploy/hostinger/deploy.sh`, `backup.sh`, `restore.sh` (sauvegarde puis réimport testés en recette), `cron.txt`.

## 5. Non testé ici / à faire par vous

| Point | Pourquoi | Action |
|---|---|---|
| Forfait Hostinger exact | inconnu | confirmer Premium ou supérieur |
| Envoi SMTP réel | pas de boîte SMTP dans la recette | créer la boîte hPanel, lancer preflight puis « Envoyer un test » |
| WhatsApp réel | compte Meta et modèles non validés | laisser `WHATSAPP_ENABLED=false` au lancement |
| Tarifs | non fixés (consigne) | les saisir et valider dans Super Admin > Offres |
| Paiement en ligne (CinetPay / PayDunya) | non codé (API à vérifier) | paiement manuel confirmé par le Super Admin |
| Intervalle 1 min (Business) | UptimeRobot FREE : 5 min minimum | garder `MONITOR_MIN_INTERVAL_MINUTES=5` ou passer UptimeRobot en payant |
| Quota UptimeRobot | 50 moniteurs en FREE | surveiller avant d'atteindre la limite |
| Sondage `pwnedpasswords` | bloqué dans la recette, l'inscription continue | vérifié par preflight sur le serveur |

Nettoyage : moniteurs de test supprimés du compte UptimeRobot, recette arrêtée.
