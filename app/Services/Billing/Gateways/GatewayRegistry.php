<?php

namespace App\Services\Billing\Gateways;

use InvalidArgumentException;

/** Prestataires déclarés dans config/billing.php (clé = code, valeur = classe). */
class GatewayRegistry
{
    public function get(string $code): PaymentGateway
    {
        $class = config("billing.gateways.$code");

        if (! is_string($class) || ! is_subclass_of($class, PaymentGateway::class)) {
            throw new InvalidArgumentException("Prestataire de paiement inconnu : $code");
        }

        return app($class);
    }

    public function has(string $code): bool
    {
        return is_string(config("billing.gateways.$code"));
    }

    /** Prestataire utilisé pour les nouvelles demandes de paiement. */
    public function default(): PaymentGateway
    {
        $gateway = $this->get((string) config('billing.default_gateway', 'manual'));

        return $gateway->isConfigured() ? $gateway : $this->get('manual');
    }
}
