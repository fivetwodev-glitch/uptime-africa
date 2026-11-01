# Déploiement sur Hostinger

**Rien n'est déployé sans accord explicite du propriétaire.** Ce guide est prêt, il n'a pas été exécuté sur Hostinger.

Sources officielles vérifiées le 2026-11-01 : [limites des forfaits](https://www.hostinger.com/support/6976044-parameters-and-limits-of-hosting-plans-in-hostinger/), [nombre de tâches cron](https://www.hostinger.com/support/1583765), [SSH](https://www.hostinger.com/support/1583645), [Composer](https://www.hostinger.com/support/5792078), [versions PHP](https://www.hostinger.com/support/1575755), [dossier racine](https://www.hostinger.com/support/1583494), [Laravel](https://www.hostinger.com/support/6152127), [SSL](https://www.hostinger.com/support/1583258), [tâches cron](https://docs.hostinger.com/websites/cron-jobs).

## 1. Compatibilité

Besoins de l'application : PHP 8.3, MySQL, **une tâche cron chaque minute**, SSH + Composer, HTTPS sortant (api.uptimerobot.com, SMTP, graph.facebook.com). Node.js n'est **pas** nécessaire sur le serveur (assets compilés en local).

| Forfait | SSH / Composer | Cron | PHP | Verdict |
|---|---|---|---|---|
| Web **Single** | non | 2 tâches max | 8.2–8.5 | **Incompatible** (pas de Composer en SSH) |
| Web **Premium** | oui | illimité, `* * * * *` accepté | 8.2–8.5 | **Compatible** |
| Web **Business / Unlimited** | oui | illimité | 8.2–8.5 | Compatible (plus de RAM, de workers) |
| **Cloud** | oui | illimité | 8.2–8.5 | Recommandé à partir de ~50 agences |
| **VPS** | root | systemd/cron | au choix | Si besoin d'un worker permanent ou d'un intervalle < 5 min à grande échelle |

Limites à connaître (Premium) : 1 Go RAM, 20 workers PHP, 360 s d'exécution web, 25 connexions MySQL par utilisateur, 3 Go par base. L'application ne tient aucun processus permanent : la file d'envoi est vidée par le cron (`QUEUE_RUN_FROM_SCHEDULER=true`, `--max-time=50`).

**Intervalle 1 minute (offre Business)** : impossible avec le compte UptimeRobot gratuit (5 min minimum). `MONITOR_MIN_INTERVAL_MINUTES=5` bloque ce choix ; passer à 1 seulement après souscription d'un plan UptimeRobot payant. `uptime:preflight` signale l'écart.

## 2. Arborescence

```
/home/u123456789/domains/votre-domaine.com/
├── uptime-africa/        ← le projet (contient .env, storage, vendor)
└── public_html  →  uptime-africa/public   (lien symbolique)
```

Le dossier racine ne peut pas être changé sur les forfaits Web/Cloud : on remplace `public_html` par un lien symbolique. Si c'est refusé, solution de repli : `deploy/hostinger/htaccess-public_html.txt`.

## 3. Premier déploiement (pas à pas)

### Étape 1 — hPanel
1. **Sites web > Tableau de bord > Avancé > Configuration PHP** : choisir **PHP 8.3**. Extensions : `pdo_mysql`, `mbstring`, `openssl`, `curl`, `fileinfo` actives.
2. **Bases de données > MySQL** : créer la base et l'utilisateur (mot de passe fort). Noter nom, utilisateur, mot de passe ; hôte `localhost`.
3. **Emails** : créer `alertes@votre-domaine.com` (expéditeur des alertes).
4. **Avancé > Accès SSH** : activer, ajouter votre clé publique.
5. **Domaines** : le domaine doit pointer vers Hostinger. **Sécurité > SSL** : le certificat gratuit s'installe automatiquement ; activer « Forcer HTTPS ».

### Étape 2 — compiler les assets (sur votre poste)
```bash
git clone https://github.com/fivetwodev-glitch/uptime-africa.git && cd uptime-africa
npm ci && npm run build          # produit public/build
tar czf build.tgz public/build
scp -P 65002 build.tgz u123456789@IP_DU_SERVEUR:~/
```
(Port et IP SSH : hPanel > Accès SSH.)

### Étape 3 — code sur le serveur (SSH)
```bash
ssh -p 65002 u123456789@IP_DU_SERVEUR
cd ~/domains/votre-domaine.com
git clone https://github.com/fivetwodev-glitch/uptime-africa.git
cd uptime-africa
tar xzf ~/build.tgz && rm ~/build.tgz
php -v                            # doit afficher 8.3 ; sinon utiliser le chemin PHP 8.3 indiqué dans hPanel
composer2 install --no-dev --optimize-autoloader --no-interaction   # ou « composer » s'il est en v2
```
Dépôt privé : utiliser une clé de déploiement GitHub en lecture seule (Settings > Deploy keys).

### Étape 4 — fichier .env
```bash
cp .env.production.example .env
chmod 600 .env
nano .env                         # remplir les variables (section 4)
php artisan key:generate --force
```
Les secrets se saisissent **uniquement** dans ce fichier, sur le serveur.

### Étape 5 — base de données
```bash
php artisan migrate --force
php artisan db:seed --force       # rôles + super administrateur (SUPER_ADMIN_EMAIL)
```
Le seeder affiche un mot de passe provisoire du super admin : le changer à la première connexion (« Mot de passe oublié » fonctionne aussi), puis vider l'historique du terminal (`history -c`).

### Étape 6 — dossiers et caches
```bash
chmod -R 775 storage bootstrap/cache
php artisan config:cache && php artisan route:cache && php artisan view:cache && php artisan event:cache
```

### Étape 7 — publier
```bash
cd ~/domains/votre-domaine.com
mv public_html public_html.ancien
ln -s uptime-africa/public public_html
```
Vérifier : `https://votre-domaine.com` affiche la page d'accueil ; `https://votre-domaine.com/.env` répond 404.

### Étape 8 — tâches cron
hPanel > **Avancé > Tâches Cron** > « Personnalisé », coller les 2 lignes de `deploy/hostinger/cron.txt` (chemin adapté). Après 2 minutes :
```bash
php artisan schedule:list
tail storage/logs/laravel-$(date +%F).log
```

### Étape 9 — contrôle final (go / no-go)
```bash
php artisan uptime:preflight --network
```
Mise en service seulement si : **0 erreur bloquante**. Puis, dans l'application :
1. Super Admin > Offres : saisir et valider les tarifs.
2. Inscrire une agence test, ajouter un site, vérifier la création du moniteur UptimeRobot.
3. Notifications > Envoyer un test : l'email doit arriver (vérifier les spams, SPF/DKIM dans hPanel > Emails).
4. Créer une sonde externe sur `https://votre-domaine.com/up` + `HEARTBEAT_URL` (ex. moniteur heartbeat UptimeRobot) pour être alerté si le cron s'arrête.

## 4. Variables d'environnement

Modèle complet : `.env.production.example`. Obligatoires :

| Variable | Valeur | Secret |
|---|---|---|
| `APP_ENV` / `APP_DEBUG` | `production` / `false` | |
| `APP_KEY` | généré par `key:generate` | oui |
| `APP_URL` | `https://votre-domaine.com` | |
| `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | base hPanel | mot de passe |
| `SESSION_DRIVER=database`, `SESSION_SECURE_COOKIE=true`, `SESSION_ENCRYPT=true` | | |
| `CACHE_STORE=database`, `QUEUE_CONNECTION=database`, `QUEUE_RUN_FROM_SCHEDULER=true` | **sans cette dernière, aucune alerte ne part** | |
| `MAIL_MAILER=smtp`, `MAIL_SCHEME=smtps`, `MAIL_HOST=smtp.hostinger.com`, `MAIL_PORT=465`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS` | boîte hPanel | mot de passe |
| `ZITE_UPTIMEROBOT_API_KEY` | clé principale du compte UptimeRobot | oui |
| `SUPER_ADMIN_EMAIL` | votre adresse | |
| `MONITOR_MIN_INTERVAL_MINUTES` | `5` (compte UptimeRobot gratuit) | |

Optionnelles : `HEARTBEAT_URL`, `UPTIME_SUPPORT_EMAIL`, `UPTIME_SYNC_TRIGGER_TOKEN` (32+ caractères, seulement sans cron), `WHATSAPP_*` (après validation Meta, voir `docs/notifications.md`), `BILLING_*` (voir `docs/abonnements.md`).

## 5. Mises à jour

```bash
# poste local : npm run build puis envoyer public/build (étape 2)
ssh … && cd ~/domains/votre-domaine.com/uptime-africa
bash deploy/hostinger/deploy.sh
```
Le script : vérifie `.env`, PHP 8.3, Composer et les assets ; **sauvegarde la base** ; maintenance ; `git pull` ; `composer install` ; migrations ; caches ; remise en ligne ; `uptime:preflight --network`.

Retour arrière : `git checkout <commit précédent>`, `bash deploy/hostinger/deploy.sh` sans pull (ou restauration, section 6) si une migration a changé la base.

## 6. Sauvegardes et restauration

| Quoi | Comment | Fréquence |
|---|---|---|
| Base MySQL | `deploy/hostinger/backup.sh` (cron 2 h 30) → `~/sauvegardes/uptime-africa/*.sql.gz`, droits 600, 14 jours | quotidienne + avant chaque déploiement |
| Sauvegardes Hostinger | hPanel > Fichiers > Sauvegardes (fréquence selon forfait) | automatique |
| Copie hors serveur | télécharger une sauvegarde par semaine (`scp`) | hebdomadaire |
| `.env` | copie chiffrée dans votre gestionnaire de mots de passe | à chaque changement |

Restauration :
```bash
cd ~/domains/votre-domaine.com/uptime-africa
ls ~/sauvegardes/uptime-africa/
bash deploy/hostinger/restore.sh ~/sauvegardes/uptime-africa/2026-11-01_0230.sql.gz   # demande « RESTAURER »
php artisan uptime:preflight --network
```
Le script sauvegarde d'abord l'état actuel. Testé en recette : sauvegarde puis réimport identique (sites, contrôles).

Si `APP_KEY` est perdue, les sessions et les secrets chiffrés deviennent illisibles : la conserver avec la sauvegarde de `.env`.

## 7. Journaux

| Fichier | Contenu |
|---|---|
| `storage/logs/laravel-AAAA-MM-JJ.log` | erreurs applicatives (`LOG_LEVEL=warning`, 30 jours) |
| Super Admin > Journal | actions sensibles (connexions admin, abonnements, suspensions) |
| Paramètres > Synchronisation | historique des passages UptimeRobot |
| Notifications > Historique | chaque envoi (envoyé, en échec, ignoré) |
| `storage/logs/backup.log` | sauvegardes |

Aucun secret n'est écrit dans les journaux (clé UptimeRobot, mots de passe, jetons).

## 8. Maintenance

| Fréquence | Action |
|---|---|
| Chaque jour | Super Admin > Statistiques (paiements en attente) ; alertes du heartbeat |
| Chaque semaine | `php artisan uptime:preflight --network` ; lire le dernier `laravel-*.log` ; télécharger une sauvegarde |
| Chaque mois | `composer audit` en local ; mises à jour de sécurité (branche + tests + `deploy.sh`) ; quota UptimeRobot (50 moniteurs en gratuit) |
| Chaque trimestre | test de restauration sur une base vide ; rotation de `ZITE_UPTIMEROBOT_API_KEY` et du mot de passe SMTP |

Le compte UptimeRobot gratuit est limité à **50 moniteurs** : au-delà, la création de site échoue avec un message clair. Passer à un plan payant avant d'atteindre la limite.

## 9. Dépannage

| Symptôme | Cause probable | Vérification / correction |
|---|---|---|
| Erreur 500 | `.env` incomplet, cache obsolète, droits | `tail storage/logs/laravel-*.log` ; `php artisan optimize:clear && php artisan config:cache` ; `chmod -R 775 storage bootstrap/cache` |
| Page sans style | `public/build` absent | renvoyer les assets (étape 2) |
| « 419 Page expirée » | session expirée ou `APP_URL`/HTTPS incohérent | `APP_URL` en https, `SESSION_SECURE_COOKIE=true`, SSL actif |
| Boucle de redirection | « Forcer HTTPS » + proxy | désactiver le double forçage, garder celui de hPanel |
| Statuts figés | cron arrêté | hPanel > Tâches Cron ; `php artisan schedule:run` à la main ; preflight « Dernier passage du cron » |
| Aucun email | `QUEUE_RUN_FROM_SCHEDULER=false`, SMTP refusé | preflight ; `php artisan queue:work --stop-when-empty` ; Notifications > Historique (erreur affichée) |
| « Moniteur introuvable » | moniteur supprimé chez UptimeRobot | page du site > Synchroniser (le recrée) |
| « URL différente chez UptimeRobot » | modification non répercutée | réenregistrer le site (le moniteur est mis à jour) |
| Création de site refusée | limite de l'offre ou des 50 moniteurs | Abonnement ; compte UptimeRobot |
| `php artisan` en PHP 8.1/8.2 | CLI différente du web | utiliser le binaire PHP 8.3 indiqué dans hPanel, ex. `PHP_BIN=<chemin> bash deploy/hostinger/deploy.sh` |
| `Too many connections` | 25 connexions MySQL (Premium) | réduire les workers ; passer à Business/Cloud |
| Site en maintenance après un échec | `deploy.sh` interrompu | `php artisan up` |

Escalade : ouvrir un ticket Hostinger (chat hPanel) en précisant « tâche cron chaque minute » et « sortie HTTPS vers api.uptimerobot.com ».
