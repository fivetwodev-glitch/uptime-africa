<?php

namespace App\Enums;

enum CheckErrorType: string
{
    case Timeout = 'timeout';
    case Dns = 'dns';
    case Connection = 'connection';
    case Ssl = 'ssl';
    case UnexpectedStatus = 'unexpected_status';
    case TooManyRedirects = 'too_many_redirects';
    case Blocked = 'blocked';

    public function label(): string
    {
        return match ($this) {
            self::Timeout => 'Délai dépassé',
            self::Dns => 'Erreur DNS',
            self::Connection => 'Connexion impossible',
            self::Ssl => 'Erreur de certificat SSL',
            self::UnexpectedStatus => 'Code HTTP inattendu',
            self::TooManyRedirects => 'Trop de redirections',
            self::Blocked => 'Adresse refusée (sécurité)',
        };
    }
}
