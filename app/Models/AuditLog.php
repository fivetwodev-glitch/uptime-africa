<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Context;

class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['agency_id', 'user_id', 'action', 'subject_type', 'subject_id', 'metadata', 'ip_address', 'request_id'];

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }

    /**
     * Enregistre une action sensible (création, suppression, changement de rôle…).
     */
    public static function record(string $action, ?Model $subject = null, array $metadata = []): self
    {
        $user = auth()->user();

        return static::create([
            'agency_id' => $subject?->getAttribute('agency_id') ?? $user?->agency_id,
            'user_id' => $user?->id,
            'action' => $action,
            'subject_type' => $subject ? $subject::class : null,
            'subject_id' => $subject ? (string) $subject->getKey() : null,
            'metadata' => $metadata ?: null,
            'ip_address' => request()?->ip(),
            'request_id' => Context::get('request_id'),
        ]);
    }
}
