<?php

namespace App\Models;

use App\Enums\IncidentStatus;
use App\Enums\IncidentType;
use App\Models\Concerns\BelongsToAgency;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Incident extends Model
{
    use BelongsToAgency, HasFactory, HasUlids;

    protected $fillable = [
        'site_id', 'agency_id', 'type', 'status', 'description', 'started_at', 'resolved_at',
        'confirmed_failures', 'last_http_status', 'last_error_message',
    ];

    protected $attributes = [
        'status' => 'open',
        'confirmed_failures' => 0,
    ];

    protected function casts(): array
    {
        return [
            'type' => IncidentType::class,
            'status' => IncidentStatus::class,
            'started_at' => 'datetime',
            'resolved_at' => 'datetime',
            'acknowledged_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Incident $incident) {
            if ($incident->agency_id === null && $incident->site_id !== null) {
                $incident->agency_id = Site::withoutGlobalScopes()->whereKey($incident->site_id)->value('agency_id');
            }

            // Statut et date de résolution restent cohérents (contrainte aussi en base MySQL).
            if ($incident->resolved_at !== null) {
                $incident->status = IncidentStatus::Resolved;
                $incident->duration_seconds = (int) $incident->started_at->diffInSeconds($incident->resolved_at, true);
            } elseif ($incident->status === IncidentStatus::Resolved) {
                $incident->resolved_at = now();
                $incident->duration_seconds = (int) $incident->started_at->diffInSeconds($incident->resolved_at, true);
            }
        });
    }

    public function resolve(?\DateTimeInterface $at = null): void
    {
        $this->resolved_at = $at ?? now();
        $this->save();
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNull('resolved_at');
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function acknowledgedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }

    public function notificationLogs(): HasMany
    {
        return $this->hasMany(NotificationLog::class);
    }
}
