<?php

namespace App\Models;

use App\Enums\BillingPeriod;
use App\Enums\PlanFeature;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    protected $fillable = [
        'code', 'name', 'audience', 'tagline', 'monthly_price_xof', 'yearly_price_xof', 'price_validated',
        'max_sites', 'max_clients', 'max_users', 'min_interval_minutes', 'retention_days',
        'includes_whatsapp', 'whatsapp_monthly_quota', 'features', 'is_public', 'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'includes_whatsapp' => 'boolean',
            'price_validated' => 'boolean',
            'features' => 'array',
            'is_public' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function formattedMonthlyPrice(): string
    {
        return $this->price_validated ? Money::xof($this->monthly_price_xof) : 'Tarif à valider';
    }

    public function hasFeature(PlanFeature $feature): bool
    {
        return in_array($feature->value, $this->features ?? [], true);
    }

    /** Prix figé à la souscription ; null si le tarif n'est pas validé ou si la période n'est pas proposée. */
    public function priceFor(BillingPeriod $period): ?int
    {
        if (! $this->price_validated) {
            return null;
        }

        return $period === BillingPeriod::Yearly ? $this->yearly_price_xof : $this->monthly_price_xof;
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }
}
