<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    protected $fillable = [
        'code', 'name', 'monthly_price_xof', 'max_sites', 'min_interval_minutes',
        'retention_days', 'includes_whatsapp', 'is_public',
    ];

    protected function casts(): array
    {
        return [
            'includes_whatsapp' => 'boolean',
            'is_public' => 'boolean',
        ];
    }

    public function formattedPrice(): string
    {
        return Money::xof($this->monthly_price_xof);
    }
}
