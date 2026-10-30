<?php

namespace App\Models;

use App\Enums\AlertStatus;
use App\Models\Concerns\BelongsToAgency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Alert extends Model
{
    use BelongsToAgency;

    protected $fillable = ['incident_id', 'notification_channel_id', 'event', 'status', 'attempts', 'last_error', 'sent_at'];

    protected function casts(): array
    {
        return [
            'status' => AlertStatus::class,
            'sent_at' => 'datetime',
        ];
    }

    public function incident(): BelongsTo
    {
        return $this->belongsTo(Incident::class);
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(NotificationChannel::class, 'notification_channel_id');
    }
}
