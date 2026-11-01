<?php

namespace App\Enums;

enum SyncResult: string
{
    case Ok = 'ok';
    case ApiError = 'api_error';
    case MonitorMissing = 'monitor_missing';
    case UrlMismatch = 'url_mismatch';

    public function label(): string
    {
        return match ($this) {
            self::Ok => 'Synchronisé',
            self::ApiError => 'API injoignable',
            self::MonitorMissing => 'Moniteur introuvable',
            self::UrlMismatch => 'URL différente chez UptimeRobot',
        };
    }
}
