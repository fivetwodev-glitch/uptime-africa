<?php

namespace App\Models;

use App\Enums\CheckErrorType;
use App\Models\Concerns\BelongsToAgency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Check extends Model
{
    use BelongsToAgency;

    public $timestamps = false;

    protected $fillable = [
        'site_id', 'agency_id', 'checked_at', 'is_up', 'http_status',
        'response_time_ms', 'error_type', 'error_message',
    ];

    protected function casts(): array
    {
        return [
            'checked_at' => 'datetime',
            'is_up' => 'boolean',
            'error_type' => CheckErrorType::class,
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }
}
