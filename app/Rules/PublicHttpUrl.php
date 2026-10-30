<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * URL surveillable : http(s), domaine public ou IP publique, ports web standard.
 * Première barrière anti-SSRF ; la résolution DNS sera revérifiée
 * au moment de chaque contrôle par le moteur.
 */
class PublicHttpUrl implements ValidationRule
{
    private const BLOCKED_SUFFIXES = ['.local', '.localhost', '.internal', '.lan', '.home', '.corp', '.intranet'];

    private const ALLOWED_PORTS = [80, 443, 8080, 8443];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $parts = is_string($value) ? parse_url(trim($value)) : false;

        if (! $parts || ! isset($parts['scheme'], $parts['host'])) {
            $fail('L\'adresse doit être une URL complète, par exemple https://www.monsite.ci');

            return;
        }

        if (! in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
            $fail('Seules les adresses http:// et https:// peuvent être surveillées.');

            return;
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            $fail('L\'adresse ne doit pas contenir d\'identifiants.');

            return;
        }

        if (isset($parts['port']) && ! in_array((int) $parts['port'], self::ALLOWED_PORTS, true)) {
            $fail('Seuls les ports 80, 443, 8080 et 8443 sont autorisés.');

            return;
        }

        $host = rtrim(strtolower(trim($parts['host'], '[]')), '.');

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            if (! filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                $fail('Cette adresse IP est privée ou réservée et ne peut pas être surveillée.');
            }

            return;
        }

        foreach (self::BLOCKED_SUFFIXES as $suffix) {
            if ($host === ltrim($suffix, '.') || str_ends_with($host, $suffix)) {
                $fail('Les adresses internes ne peuvent pas être surveillées.');

                return;
            }
        }

        if (! preg_match('/^([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z][a-z0-9-]{0,61}[a-z0-9]$/', $host)) {
            $fail('Le nom de domaine est invalide.');
        }
    }
}
