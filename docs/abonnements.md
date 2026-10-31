# Abonnements, paiements et administration générale

## Offres

Trois offres insérées une seule fois (`config/plans.php`, `PlanCatalog::sync`). Ensuite, **l'espace Super Admin fait foi** : la configuration n'écrase jamais une offre modifiée.

| | Starter | Pro | Business |
|---|---|---|---|
| Public | indépendants | agences web | grandes agences |
| Sites | 5 | 25 | 100 |
| Clients | 3 | illimité | illimité |
| Utilisateurs | 1 | 3 | 15 |
| Intervalle minimal | 5 min | 5 min | 1 min |
| Historique consultable | 30 j | 90 j | 365 j |
| WhatsApp | non | 300 / mois | 1 500 / mois |
| Fonctionnalités | standard | plusieurs clients, notifications avancées | + équipes, rapports avancés, priorité |

Ces valeurs sont des **propositions** modifiables dans l'administration (Offres > Modifier).

**Tarifs** : aucun n'est fixé. Les prix valent 0 et `price_validated` est faux : l'offre affiche « Tarif à venir » et ne peut pas être souscrite en payant. Le Super Admin saisit les prix puis coche « Tarif validé » (action journalisée `plan.price_validated`).

## Limites appliquées

| Limite | Où |
|---|---|
| Sites, clients, utilisateurs | création refusée au-delà (`PlanLimits::ensureCanAdd`) |
| Intervalle minimal | liste des intervalles du formulaire de site |
| Historique | incidents et contrôles affichés sur la durée de l'offre (rien n'est supprimé) |
| WhatsApp | ajout d'un destinataire WhatsApp refusé ; alertes WhatsApp tracées « ignorées » si l'offre ne l'inclut plus |
| Notifications avancées | alertes de panne prolongée et résumés périodiques |
| Rapports avancés, priorité | indicateurs d'offre ; pas encore de fonctionnalité dédiée |

Sans abonnement en cours (essai, actif, paiement en retard) : aucune création possible ; **la surveillance des sites existants continue**.

## Cycle de vie

| Statut | Sens |
|---|---|
| En attente de paiement | demande de souscription / changement de plan, pas encore active |
| Période d'essai | à l'inscription : offre `BILLING_TRIAL_PLAN` (pro) pendant `BILLING_TRIAL_DAYS` (14) jours |
| Actif | payé jusqu'à la date d'expiration |
| Paiement en retard | échéance dépassée, accès maintenu `BILLING_GRACE_DAYS` (7) jours |
| Annulé / Expiré | terminé |

- Une seule souscription en cours par agence (index unique en base).
- Le prix est **figé** à la souscription : modifier une offre ne change pas le prix des abonnés.
- **Changement de plan** (agence) : crée un abonnement « en attente » + un paiement en attente. La formule actuelle reste active jusqu'à confirmation du paiement, puis la nouvelle période démarre (pas de prorata). Une descente de gamme est refusée si l'utilisation dépasse les limites de la nouvelle offre.
- **Renouvellement** : prolonge à partir de l'expiration actuelle si elle est future, sinon à partir du paiement.
- `subscriptions:check` (chaque heure) : fins d'essai → expiré ; échéance dépassée → en retard ; fin du délai de grâce → expiré.
- Super Admin : créer un abonnement (essai ou actif), changer d'offre immédiatement (échéance conservée), enregistrer un renouvellement, annuler.

## Paiements

Table `payments` : agence, abonnement, objet (souscription / renouvellement), montant FCFA, statut, prestataire, référence externe (unique par prestataire : anti-doublon), moyen, période couverte, auteur de la saisie.

Aujourd'hui, seul le prestataire **`manual`** est actif : l'agence demande, l'équipe reçoit le règlement (Mobile Money, virement…) puis clique « Confirmer le paiement reçu » en saisissant la référence de transaction. Confirmation idempotente.

### Intégrer CinetPay ou PayDunya plus tard

Architecture prête (`app/Services/Billing/Gateways`) :

1. **Vérifier l'API officielle** du prestataire (documentation à jour, mode test, signature des notifications, statuts, devise XOF, frais).
2. Écrire une classe qui implémente `PaymentGateway` :
   - `checkoutUrl()` : initialise la transaction chez le prestataire et renvoie l'URL de paiement ;
   - `handleWebhook()` : vérifie la signature **et** re-interroge l'API du prestataire avant de renvoyer un `GatewayResult`.
3. La déclarer dans `config/billing.php` (`gateways`) et définir `BILLING_GATEWAY`.
4. Identifiants dans le `.env` du serveur uniquement (jamais en base, jamais dans le navigateur).
5. Ajouter la route de notification (POST, hors CSRF, limitée en débit) qui appelle `SubscriptionManager::markPaid` : la règle d'activation reste unique.

Rien n'a été codé pour CinetPay ou PayDunya : leurs API n'ont pas encore été vérifiées.

## Espace Super Admin (`/admin`)

Accès : permission `admin.access`, réservée au rôle super administrateur (middleware `can:admin.access` + contrôle dans chaque action). Les administrateurs d'agence et collaborateurs reçoivent une erreur 403.

| Page | Contenu |
|---|---|
| Statistiques | agences, utilisateurs, sites, incidents, abonnements par statut et par offre, revenu mensuel récurrent, encaissé du mois, paiements en attente |
| Agences | liste, offre en cours, suspension / réactivation |
| Utilisateurs | toutes agences, recherche, désactivation (sessions fermées) |
| Sites, Incidents | toutes agences, filtres |
| Offres | limites, fonctionnalités, tarifs, validation |
| Abonnements | paiements en attente, création, détail, changement d'offre, renouvellement, annulation |
| Journal | toutes les actions sensibles (auteur, agence, objet, détails, IP) |

Actions journalisées : `plan.updated`, `plan.price_validated`, `subscription.created|requested|renewal_requested|plan_changed|cancelled|trial_expired|past_due|expired`, `payment.confirmed|cancelled`, `agency.suspended|reactivated`, `user.deactivated_by_admin|reactivated_by_admin`.
