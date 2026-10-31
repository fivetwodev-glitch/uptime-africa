<?php

namespace App\Services\Billing\Gateways;

use App\Models\Payment;
use Illuminate\Http\Request;

/**
 * Contrat d'un prestataire de paiement (Mobile Money, carte…).
 *
 * Un futur prestataire (CinetPay, PayDunya…) implémente cette interface APRÈS
 * vérification de son API officielle, puis s'enregistre dans config/billing.php.
 * La confirmation d'un paiement passe toujours par PaymentService::markPaid,
 * jamais par le prestataire directement : une seule règle d'activation.
 */
interface PaymentGateway
{
    /** Identifiant stocké dans payments.provider (ex. « manual »). */
    public function code(): string;

    public function label(): string;

    /** Identifiants présents côté serveur (jamais en base ni dans le navigateur). */
    public function isConfigured(): bool;

    /** Paiement en ligne : URL de redirection vers la page du prestataire ; null = paiement hors ligne. */
    public function checkoutUrl(Payment $payment): ?string;

    /**
     * Notification serveur à serveur du prestataire. Doit vérifier la signature
     * ET re-interroger l'API du prestataire avant de renvoyer un résultat.
     */
    public function handleWebhook(Request $request): ?GatewayResult;
}
