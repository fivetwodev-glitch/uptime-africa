<?php

namespace App\Models;

use App\Enums\ChannelType;
use App\Models\Concerns\BelongsToAgency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NotificationChannel extends Model
{
    use BelongsToAgency;

    protected $fillable = ['type', 'label', 'destination', 'is_active'];

    protected function casts(): array
    {
        return [
            'type' => ChannelType::class,
            'verified_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function preferences(): HasMany
    {
        return $this->hasMany(NotificationPreference::class);
    }
}
