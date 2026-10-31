<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAgency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationPreference extends Model
{
    use BelongsToAgency;

    protected $fillable = [
        'notification_channel_id', 'site_id', 'notify_on_down', 'notify_on_recovery',
        'delay_minutes', 'is_active', 'notify_on_prolonged', 'prolonged_after_minutes',
    ];

    protected function casts(): array
    {
        return [
            'notify_on_down' => 'boolean',
            'notify_on_recovery' => 'boolean',
            'notify_on_prolonged' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(NotificationChannel::class, 'notification_channel_id');
    }

    /** Null = tous les sites de l'agence. */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }
}
