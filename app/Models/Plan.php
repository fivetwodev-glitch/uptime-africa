<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    protected $fillable = [
        'code', 'name', 'monthly_price_xof', 'yearly_price_xof', 'max_sites', 'max_users',
        'min_interval_minutes', 'retention_days', 'includes_whatsapp', 'whatsapp_monthly_quota',
        'is_public', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'includes_whatsapp' => 'boolean',
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
        return Money::xof($this->monthly_price_xof);
    }
}
