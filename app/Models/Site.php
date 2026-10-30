<?php

namespace App\Models;

use App\Enums\SiteStatus;
use App\Models\Concerns\BelongsToAgency;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Site extends Model
{
    use BelongsToAgency, HasFactory, HasUlids;

    protected $fillable = [
        'client_id', 'name', 'url', 'interval_minutes', 'timeout_seconds',
        'expected_status_codes', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'status' => SiteStatus::class,
            'expected_status_codes' => 'array',
            'is_active' => 'boolean',
            'last_checked_at' => 'datetime',
            'next_check_at' => 'datetime',
        ];
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
}
