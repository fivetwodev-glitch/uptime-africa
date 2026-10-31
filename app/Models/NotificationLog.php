<?php

namespace App\Models;

use App\Enums\ChannelType;
use App\Enums\NotificationEvent;
use App\Enums\NotificationStatus;
use App\Models\Concerns\BelongsToAgency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationLog extends Model
{
    use BelongsToAgency;

    protected $fillable = [
        'site_id', 'incident_id', 'notification_channel_id', 'channel_type', 'recipient',
        'event', 'status', 'attempts', 'provider_message_id', 'error_message', 'sent_at',
        'dedup_key', 'template', 'provider', 'delivered_at', 'read_at',
    ];

    protected $attributes = [
        'status' => 'queued',
        'attempts' => 0,
    ];

    protected function casts(): array
    {
        return [
            'channel_type' => ChannelType::class,
            'event' => NotificationEvent::class,
            'status' => NotificationStatus::class,
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
            'read_at' => 'datetime',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
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
