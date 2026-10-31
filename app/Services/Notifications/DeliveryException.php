<?php

namespace App\Services\Notifications;

use RuntimeException;

/** Échec d'envoi. retryable : un nouvel essai a des chances d'aboutir sans risque de doublon. */
class DeliveryException extends RuntimeException
{
    public function __construct(string $message, public readonly bool $retryable = false, public readonly ?int $providerCode = null)
    {
        parent::__construct($message);
    }
}
