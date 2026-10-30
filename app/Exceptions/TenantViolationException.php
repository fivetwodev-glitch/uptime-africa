<?php

namespace App\Exceptions;

class TenantViolationException extends UptimeException
{
    public function __construct()
    {
        parent::__construct(
            userMessage: 'Cette ressource n\'est pas accessible.',
            status: 404,
            logMessage: 'Tentative d\'écriture sur une autre agence.',
        );
    }
}
