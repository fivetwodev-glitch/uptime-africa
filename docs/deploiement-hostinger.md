# Déploiement Hostinger (préparé, NON exécuté)

Rien n'est déployé tant que la phase 0 n'est pas validée.

## Phase 0 — vérifications à faire dans hPanel

- [ ] Version PHP 8.3 sélectionnable pour le domaine.
- [ ] Accès SSH disponible et `composer` utilisable.
- [ ] Tâche cron « chaque minute » acceptée et exécutée 60 fois par heure.
- [ ] Requêtes HTTPS sortantes (curl) autorisées depuis PHP.
- [ ] Envoi SMTP depuis une boîte Hostinger et limite quotidienne d'envoi.

## Étapes prévues

1. Créer la base MySQL et l'utilisateur dans hPanel.
2. Cloner le dépôt **hors de `public_html`** (ex. `/home/<utilisateur>/uptime-africa`).
3. Faire pointer le domaine vers `uptime-africa/public` (ou lien symbolique `public_html` → `public`).
4. Créer `.env` à partir de `.env.production.example`, puis `php artisan key:generate`.
5. Compiler les assets en local ou en CI (`npm run build`) et envoyer `public/build`.
6. Lancer `deploy/hostinger/deploy.sh`.
7. Créer la tâche cron décrite dans `deploy/hostinger/cron.txt`.
8. Configurer `HEARTBEAT_URL` vers une sonde externe.
