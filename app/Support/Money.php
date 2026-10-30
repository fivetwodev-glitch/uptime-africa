<?php

namespace App\Support;

use InvalidArgumentException;

class Money
{
    /**
     * Formate un montant entier en FCFA : 15000 → « 15 000 FCFA ».
     */
    public static function xof(int $amount): string
    {
        if ($amount < 0) {
            throw new InvalidArgumentException('Le montant ne peut pas être négatif.');
        }

        // Espace insécable fine entre les milliers, insécable avant la devise.
        return number_format($amount, 0, ',', "\u{202F}")."\u{00A0}FCFA";
    }
}
