<?php

namespace App\Models;

use App\Enums\BillingPeriod;
use App\Enums\SubscriptionStatus;
use App\Models\Concerns\BelongsToAgency;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subscription extends Model
{
    use BelongsToAgency;

    protected $fillable = [
        'plan_id', 'status', 'billing_period', 'price_xof', 'starts_at', 'ends_at',
        'trial_ends_at', 'cancelled_at', 'renewed_at', 'payment_provider', 'payment_reference', 'previous_subscription_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => SubscriptionStatus::class,
            'billing_period' => BillingPeriod::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'trial_ends_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'renewed_at' => 'datetime',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function previous(): BelongsTo
    {
        return $this->belongsTo(self::class, 'previous_subscription_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /** Date à laquelle l'accès s'arrête : fin d'essai ou échéance. */
    public function accessEndsAt(): ?CarbonInterface
    {
        return $this->status === SubscriptionStatus::Trialing ? $this->trial_ends_at : $this->ends_at;
    }
}
