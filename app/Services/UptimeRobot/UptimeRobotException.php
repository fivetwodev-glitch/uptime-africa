<?php

namespace App\Services\UptimeRobot;

use RuntimeException;

/**
 * Erreur de l'API UptimeRobot. Le message est sûr à afficher : il ne contient
 * jamais la clé API ni les en-têtes de la requête.
 */
class UptimeRobotException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?int $httpStatus = null,
        public readonly ?string $apiCode = null,
        public readonly bool $retryable = false,
    ) {
        parent::__construct($message);
    }

    public static function notConfigured(): self
    {
        return new self('La clé API UptimeRobot n\'est pas configurée sur le serveur (variable ZITE_UPTIMEROBOT_API_KEY).');
    }
}
