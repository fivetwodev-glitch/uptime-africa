<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Erreur métier : porte un message sûr pour l'utilisateur et un code HTTP.
 * Le message technique (logMessage) n'est jamais affiché.
 */
class UptimeException extends RuntimeException
{
    public function __construct(
        public readonly string $userMessage,
        public readonly int $status = 422,
        ?string $logMessage = null,
        public readonly array $context = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($logMessage ?? $userMessage, 0, $previous);
    }
}
