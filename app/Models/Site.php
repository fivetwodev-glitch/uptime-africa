<?php

namespace App\Models;

use App\Enums\MonitoringProvider;
use App\Enums\MonitoringStatus;
use App\Enums\SiteStatus;
use App\Enums\SyncStatus;
use App\Models\Concerns\BelongsToAgency;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Site extends Model
{
    use BelongsToAgency, HasFactory, HasUlids;

    protected $fillable = [
        'client_id', 'name', 'url', 'monitoring_status', 'interval_minutes', 'timeout_seconds',
        'expected_status_codes', 'monitoring_provider', 'external_monitor_id',
    ];

    protected $attributes = [
        'status' => 'unknown',
        'monitoring_status' => 'active',
        'monitoring_provider' => 'internal',
        'sync_status' => 'not_applicable',
        'external_managed' => false,
        'consecutive_failures' => 0,
    ];

    protected function casts(): array
    {
        return [
            // Les formulaires envoient des chaînes ; l'API UptimeRobot exige des nombres.
            'interval_minutes' => 'integer',
            'timeout_seconds' => 'integer',
            'status' => SiteStatus::class,
            'monitoring_status' => MonitoringStatus::class,
            'monitoring_provider' => MonitoringProvider::class,
            'sync_status' => SyncStatus::class,
            'expected_status_codes' => 'array',
            'last_checked_at' => 'datetime',
            'next_check_at' => 'datetime',
            'last_synced_at' => 'datetime',
            'external_managed' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Site $site) {
            // Hors contexte d'agence (tâches planifiées), l'agence découle du client.
            if ($site->agency_id === null && $site->client_id !== null) {
                $site->agency_id = Client::withoutGlobalScopes()->whereKey($site->client_id)->value('agency_id');
            }

            if ($site->isDirty('url')) {
                $site->url_hash = static::hashUrl($site->url);
            }

            if ($site->isDirty('monitoring_provider')) {
                $site->sync_status = $site->monitoring_provider->isExternal() ? SyncStatus::Pending : SyncStatus::NotApplicable;
            }
        });
    }

    /**
     * Empreinte de l'URL normalisée (casse du domaine, slash final).
     */
    public static function hashUrl(string $url): string
    {
        $url = trim($url);
        $parts = parse_url($url);

        if (isset($parts['scheme'], $parts['host'])) {
            $url = strtolower($parts['scheme']).'://'.strtolower($parts['host'])
                .(isset($parts['port']) ? ':'.$parts['port'] : '')
                .rtrim($parts['path'] ?? '', '/')
                .(isset($parts['query']) ? '?'.$parts['query'] : '');
        }

        return hash('sha256', $url);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function checks(): HasMany
    {
        return $this->hasMany(Check::class);
    }

    public function incidents(): HasMany
    {
        return $this->hasMany(Incident::class);
    }

    public function openIncident(): HasOne
    {
        return $this->hasOne(Incident::class)->whereNull('resolved_at');
    }

    public function dailyStats(): HasMany
    {
        return $this->hasMany(DailyStat::class);
    }
}
