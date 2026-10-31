<?php

namespace App\Services\Notifications;

use App\Enums\ChannelType;
use App\Models\Setting;

/** Paramètres de notification d'une agence (table settings, clé « notifications »). */
class NotificationSettings
{
    public const KEY = 'notifications';

    public const DIGEST_CHOICES = ['off' => 'Désactivé', 'daily' => 'Quotidien', 'weekly' => 'Hebdomadaire (lundi)'];

    private const DEFAULTS = [
        'enabled' => true,
        'channels' => ['email' => true, 'whatsapp' => true],
        'digest' => 'off',
    ];

    /** @return array{enabled:bool, channels:array{email:bool, whatsapp:bool}, digest:string} */
    public static function for(string $agencyId): array
    {
        $stored = Setting::get(self::KEY, $agencyId, []);
        $stored = is_array($stored) ? $stored : [];

        return [
            'enabled' => (bool) ($stored['enabled'] ?? self::DEFAULTS['enabled']),
            'channels' => [
                'email' => (bool) ($stored['channels']['email'] ?? true),
                'whatsapp' => (bool) ($stored['channels']['whatsapp'] ?? true),
            ],
            'digest' => array_key_exists($stored['digest'] ?? '', self::DIGEST_CHOICES) ? $stored['digest'] : 'off',
        ];
    }

    public static function save(string $agencyId, array $settings): void
    {
        Setting::put(self::KEY, $settings, $agencyId);
    }

    public static function allows(string $agencyId, ChannelType $type): bool
    {
        $settings = self::for($agencyId);

        return $settings['enabled'] && $settings['channels'][$type->value];
    }
}
