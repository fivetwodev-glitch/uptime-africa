<?php

namespace App\Services\Billing\Gateways;

use App\Enums\PaymentStatus;

/** Résultat vérifié auprès du prestataire pour un paiement. */
final class GatewayResult
{
    public function __construct(
        public readonly int $paymentId,
        public readonly PaymentStatus $status,
        public readonly ?string $providerReference = null,
        public readonly ?string $method = null,
        public readonly ?int $amountXof = null,
        public readonly array $metadata = [],
    ) {}
}
