<?php

namespace App\Models;

use App\Enums\CheckErrorType;
use App\Models\Concerns\BelongsToAgency;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Incident extends Model
{
    use BelongsToAgency, HasUlids;

    protected $fillable = [
        'site_id', 'agency_id', 'started_at', 'resolved_at', 'duration_seconds',
        'error_type', 'last_http_status', 'summary',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'resolved_at' => 'datetime',
            'error_type' => CheckErrorType::class,
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(Alert::class);
    }
}
