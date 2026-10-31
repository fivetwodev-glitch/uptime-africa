# Sécurité

Règle de base : l'interface n'est pas une barrière. Chaque route vérifie elle-même
l'authentification, l'agence et la permission ; masquer un bouton n'est qu'un confort.

## Rôles

| Rôle | Portée | Permissions principales |
| --- | --- | --- |
| Super administrateur | Plateforme (aucune agence) | `/admin` : agences, suspension / réactivation. Créé uniquement par `SUPER_ADMIN_EMAIL` + `php artisan db:seed` |
| Administrateur d'agence | Son agence | Tout : équipe, clients, sites, alertes, abonnement, paramètres |
| Collaborateur | Son agence | Clients, sites, prise en charge des incidents, lecture de l'équipe et du journal d'alertes |

Source unique : `config/permissions.php` → tables `roles`, `permissions`, `permission_role`.
Contrôle : `Gate::before` → `User::hasPermission()` ; `$this->authorize()` dans chaque contrôleur.

## Anti-élévation de privilèges

| Règle | Où |
| --- | --- |
| `role_id`, `agency_id`, `is_active` non assignables en masse | `User::$fillable` |
| Le profil n'accepte que nom, email (et mot de passe actuel) | `SettingsController::updateProfile` |
| Rôles attribuables : administrateur ou collaborateur, jamais super administrateur | `RoleName::assignableInAgency()`, `TeamController` |
| Personne ne modifie son propre rôle, ne se désactive ni ne se supprime | `TeamController` |
| L'agence garde toujours au moins un administrateur actif | `TeamController::ensureAnotherActiveAdmin` |
| L'inscription crée toujours un administrateur d'agence | `RegisterAgencyController` |
| Super administrateur ⇔ aucune agence | `User::booted()` |

## Isolation des agences

| Mesure | Où |
| --- | --- |
| Filtre automatique `agency_id` en lecture, refus en écriture croisée | `BelongsToAgency`, `AgencyScope` |
| Contexte d'agence posé avant la résolution des routes → 404 sur un identifiant étranger | `SetTenantContext` (priorité middleware) |
| `{member}` (utilisateurs) résolu uniquement dans l'agence courante | `AppServiceProvider` (`Route::bind`) |
| Clés étrangères composites `(x_id, agency_id)` | migrations |
| Agence suspendue → 403 pour tous ses utilisateurs, sessions fermées | `SetTenantContext`, `AdminController::toggleAgency` |

Vérifié par `tests/Feature/Security/CrossAgencyLeakTest.php` : deux agences complètes,
admin et collaborateur de chacune, 17 pages et filtres forgés (aucune donnée de l'autre agence affichée),
20 actions sur les données de l'autre agence (404/403, base inchangée octet par octet).

## Authentification

| Mesure | Détail |
| --- | --- |
| Hachage | bcrypt (`BCRYPT_ROUNDS=12`), cast `hashed` |
| Mot de passe | production : 10 caractères, majuscule, minuscule, chiffre, absent des fuites connues |
| Connexion | message unique (compte inconnu / mauvais mot de passe / désactivé) ; emails en minuscules |
| Force brute | 5 essais/min par compte + IP, 20/min par IP, 30/h par compte |
| Mot de passe oublié | réponse identique que le compte existe ou non ; lien à usage unique, 60 min ; 5 demandes/h par adresse, 20/h par IP ; email mis en file (pas de fuite par le temps de réponse) |
| Invitation | l'administrateur ne connaît jamais le mot de passe ; lien 48 h, accepté seulement pour un compte jamais connecté |
| Changement d'email | mot de passe actuel exigé |

## Sessions

| Mesure | Détail |
| --- | --- |
| Stockage | base de données (`SESSION_DRIVER=database`), liste visible dans Paramètres → Sécurité |
| Cookie | `HttpOnly`, `SameSite=Lax` ; production : `Secure`, contenu chiffré (`SESSION_ENCRYPT=true`) |
| Connexion | identifiant de session régénéré |
| Déconnexion | session invalidée, jeton CSRF renouvelé |
| Changement / réinitialisation du mot de passe | autres sessions fermées (`auth.session` + suppression en base) |
| Désactivation d'un utilisateur | sessions supprimées, jeton « rester connecté » renouvelé, déconnexion à la requête suivante |
| Fermer mes autres sessions | Paramètres → Sécurité (mot de passe exigé) |

## Injections, XSS, CSRF

| Risque | Protection |
| --- | --- |
| SQL | Eloquent / requêtes paramétrées ; les seuls fragments SQL bruts sont des constantes |
| XSS | échappement Blade `{{ }}` partout (aucun `{!! !!}`), CSP `script-src 'self'` |
| CSRF | `ValidateCsrfToken` sur toutes les routes web, `@csrf` dans chaque formulaire |
| SSRF | `PublicHttpUrl` sur les URL surveillées |
| Entrées | chaque action valide ses champs (types, longueurs, listes autorisées) |

## Journalisation

| Journal | Contenu |
| --- | --- |
| `audit_logs` (base) | connexion, échec de connexion, déconnexion, demande et réinitialisation de mot de passe, changement d'email ou de mot de passe, fermeture de sessions, invitation, changement de rôle, désactivation, suppression, refus d'une modification d'équipe, suspension d'agence, modifications de sites, clients, alertes |
| `storage/logs/security-*.log` | échecs de connexion, demandes et refus de réinitialisation, accès refusés (403) |

Chaque entrée porte l'utilisateur, l'agence, l'IP, le navigateur et l'identifiant de requête.

## Autres mesures

En-têtes (CSP, X-Frame-Options, nosniff, Referrer-Policy, HSTS en production), HTTPS forcé en production,
aucun détail technique affiché en production, secrets uniquement dans `.env` serveur.

À venir : vérification de l'adresse email à l'inscription, double authentification (phase 8).
