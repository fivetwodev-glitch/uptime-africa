<?php

namespace App\Enums;

enum IncidentType: string
{
    case Downtime = 'downtime';
    case Timeout = 'timeout';
    case DnsError = 'dns_error';
    case SslError = 'ssl_error';
    case HttpError = 'http_error';
    case SlowResponse = 'slow_response';

    public function label(): string
    {
        return match ($this) {
            self::Downtime => 'Site injoignable',
            self::Timeout => 'Délai dépassé',
            self::DnsError => 'Erreur DNS',
            self::SslError => 'Erreur de certificat SSL',
            self::HttpError => 'Code HTTP en erreur',
            self::SlowResponse => 'Temps de réponse trop long',
        };
    }
}
