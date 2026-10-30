<?php

namespace App\Support;

/**
 * Agence courante pour la requête en cours.
 * Null = aucun filtrage (administrateur général, tâches planifiées).
 */
class TenantContext
{
    private ?string $agencyId = null;

    public function set(?string $agencyId): void
    {
        $this->agencyId = $agencyId;
    }

    public function id(): ?string
    {
        return $this->agencyId;
    }

    public function has(): bool
    {
        return $this->agencyId !== null;
    }

    public function clear(): void
    {
        $this->agencyId = null;
    }
}
