# Sécurité

| Mesure | Où |
| --- | --- |
| Isolation stricte par agence (lecture + écriture) | `BelongsToAgency`, `AgencyScope`, `SetTenantContext` |
| `role` et `agency_id` non assignables en masse | `User::$fillable` + `Model::shouldBeStrict()` |
| En-têtes de sécurité (CSP, X-Frame-Options, nosniff, Referrer-Policy, HSTS en prod) | `SecurityHeaders` |
| HTTPS forcé en production | `AppServiceProvider` |
| Mots de passe robustes en production (10 car., casse, chiffres, non compromis) | `AppServiceProvider` |
| CSRF, échappement XSS, requêtes paramétrées | natifs Laravel |
| Aucun détail technique affiché en production | `APP_DEBUG=false` + gestionnaire d'erreurs |
| Secrets uniquement dans `.env` serveur, jamais dans le frontend | `.env.example`, préfixe `VITE_` réservé au public |
| Journal d'audit | `AuditLog` |

À venir : anti-SSRF des URL surveillées (phase 2), limitation des tentatives de connexion et
vérification email (authentification), 2FA (phase 8).
