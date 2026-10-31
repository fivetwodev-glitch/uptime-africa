# Interface

Blade + Tailwind 4, sans framework JavaScript. Tout le JS est dans `resources/js/app.js`
(la politique CSP interdit les scripts en ligne). Aucune clé ni secret côté navigateur.

## Identité

| Élément | Valeur |
| --- | --- |
| Bleu nuit (structure, menu) | `night-900` `#0a1733` |
| Bleu électrique (actions) | `electric-500` `#2f6bff` |
| Fond / contenu | blanc, `slate-50` |
| Statuts | vert = en ligne, rouge = hors ligne, ambre = dégradé, gris = inconnu / en pause |
| Logo | `resources/views/components/logo.blade.php` (SVG original) |

Jetons et classes (`card`, `btn-primary`, `input`, `data-table`…) : `resources/css/app.css`.

## Pages

| Menu | Route | URL | Permission |
| --- | --- | --- | --- |
| Vue générale | `dashboard` | `/tableau-de-bord` | connecté |
| Sites surveillés | `sites.*` | `/sites` | `sites.view` (écriture : `sites.manage`) |
| Clients | `clients.*` | `/clients` | `clients.view` (écriture : `clients.manage`) |
| Incidents | `incidents.*` | `/incidents` | `incidents.view` (prise en charge : `incidents.acknowledge`) |
| Contrôles | `checks.index` | `/controles` | `sites.view` |
| Notifications | `notifications.*` | `/notifications` | `notifications.view` (écriture : `notifications.manage`) |
| Abonnement | `subscription.show` | `/abonnement` | `subscriptions.view` |
| Paramètres | `settings.*`, `team.*` | `/parametres`, `/parametres/securite`, `/parametres/equipe` | connecté (agence : `agency.update`, équipe : `users.view` / `users.manage`) |
| Administration | `admin.index` | `/admin` | `admin.access` (super admin uniquement) |

Publiques : `/`, `/connexion`, `/inscription`, `/mot-de-passe-oublie`, `/reinitialisation/{jeton}`. Le menu masque les entrées non autorisées ;
les contrôleurs vérifient la permission (403 sinon).

## Règles de données

- Tous les chiffres viennent de la base (`DashboardController`, `UptimeStats`).
- Sans données : état vide explicite et `—`, jamais de valeur par défaut inventée.
- Disponibilité = contrôles réussis / contrôles effectués sur la période.
- Temps de réponse moyen = contrôles réussis uniquement.
- Graphique de temps de réponse : SVG généré côté serveur (24 h, ≥ 2 mesures).

## Actions disponibles

| Écran | Actions |
| --- | --- |
| Sites | ajouter, modifier, pause / reprise, supprimer (confirmation), recherche, filtres statut / surveillance / client |
| Clients | ajouter, modifier, supprimer (supprime aussi ses sites, confirmation), recherche |
| Incidents | filtres statut / type / site, prise en charge |
| Contrôles | filtres site / résultat / période |
| Notifications | ajouter un destinataire email ou WhatsApp, préférences (panne, rétablissement, délai), activer / désactiver, supprimer, journal filtrable |
| Paramètres | profil, agence, mot de passe, sessions ouvertes ; équipe : inviter, modifier le rôle, désactiver, renvoyer l'invitation, supprimer |

## Volontairement absent (phase suivante)

| Fonction | Affichage actuel |
| --- | --- |
| Moteur de contrôles | états vides « aucun contrôle » |
| Envoi des alertes / vérification des destinataires | « Non vérifié », journal vide |
| Paiement en ligne | pas de bouton d'achat ; lien email si `UPTIME_SUPPORT_EMAIL` est renseigné |

## Sécurité de l'interface

- `SetTenantContext` passe avant la liaison des routes : un identifiant d'une autre agence renvoie 404.
- URL surveillée : `PublicHttpUrl` (http/https, ports 80/443/8080/8443, pas d'IP privée ni de domaine interne).
- Authentification, rôles et sessions : voir [securite.md](securite.md).
- Compte désactivé : déconnecté à la requête suivante.
