<?php

namespace App\Models;

use App\Enums\ChannelType;
use App\Models\Concerns\BelongsToAgency;
use Illuminate\Database\Eloquent\Model;

class NotificationChannel extends Model
{
    use BelongsToAgency;

    protected $fillable = ['type', 'destination', 'is_active'];

    protected function casts(): array
    {
        return [
            'type' => ChannelType::class,
            'verified_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }
}
