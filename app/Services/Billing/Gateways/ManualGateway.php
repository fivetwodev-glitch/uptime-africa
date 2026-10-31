<?php

namespace App\Services\Billing\Gateways;

use App\Models\Payment;
use Illuminate\Http\Request;

/**
 * Paiement hors ligne (virement, Mobile Money reçu sur le compte de l'entreprise) :
 * l'équipe Uptime Africa constate la réception et enregistre le paiement dans
 * l'espace Super Admin. Seul prestataire actif tant qu'aucune API n'est intégrée.
 */
class ManualGateway implements PaymentGateway
{
    public function code(): string
    {
        return 'manual';
    }

    public function label(): string
    {
        return 'Paiement hors ligne (enregistré par l\'équipe)';
    }

    public function isConfigured(): bool
    {
        return true;
    }

    public function checkoutUrl(Payment $payment): ?string
    {
        return null;
    }

    public function handleWebhook(Request $request): ?GatewayResult
    {
        return null;
    }
}
