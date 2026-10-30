<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAgency;
use Illuminate\Database\Eloquent\Model;

class DailyStat extends Model
{
    use BelongsToAgency;

    public $timestamps = false;

    protected $fillable = [
        'site_id', 'agency_id', 'date', 'checks_total', 'checks_up',
        'uptime_percent', 'avg_response_ms', 'incidents_count',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'uptime_percent' => 'decimal:2',
        ];
    }
}
