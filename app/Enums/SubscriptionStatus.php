<?php

namespace App\Enums;

enum SubscriptionStatus: string
{
    case Trialing = 'trialing';
    case Active = 'active';
    case PastDue = 'past_due';
    case Cancelled = 'cancelled';
    case Expired = 'expired';

    /** Statuts « en cours » : une seule souscription de ce type par agence. */
    public function isCurrent(): bool
    {
        return in_array($this, [self::Trialing, self::Active, self::PastDue], true);
    }
}
