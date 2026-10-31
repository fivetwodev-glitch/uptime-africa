<?php

namespace App\Enums;

enum PaymentPurpose: string
{
    case Activation = 'activation';
    case Renewal = 'renewal';

    public function label(): string
    {
        return match ($this) {
            self::Activation => 'Souscription / changement de plan',
            self::Renewal => 'Renouvellement',
        };
    }
}
