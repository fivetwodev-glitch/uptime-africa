<?php

namespace App\Models\Concerns;

use App\Exceptions\TenantViolationException;
use App\Models\Agency;
use App\Models\Scopes\AgencyScope;
use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Isole automatiquement les données par agence :
 * - lecture : filtre sur l'agence courante ;
 * - écriture : agency_id rempli d'office, toute autre agence refusée.
 */
trait BelongsToAgency
{
    public static function bootBelongsToAgency(): void
    {
        static::addGlobalScope(new AgencyScope);

        static::saving(function ($model) {
            $tenant = app(TenantContext::class);

            if (! $tenant->has()) {
                return;
            }

            if ($model->agency_id === null) {
                $model->agency_id = $tenant->id();
            }

            if ($model->agency_id !== $tenant->id()) {
                throw new TenantViolationException;
            }
        });

        static::deleting(function ($model) {
            $tenant = app(TenantContext::class);

            if ($tenant->has() && $model->agency_id !== $tenant->id()) {
                throw new TenantViolationException;
            }
        });
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }
}
