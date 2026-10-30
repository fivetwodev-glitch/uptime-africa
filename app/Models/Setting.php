<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Paramètres clé/valeur. agency_id NULL = paramètre global de la plateforme.
 * Pas de scope automatique : l'agence est toujours passée explicitement.
 */
class Setting extends Model
{
    protected $fillable = ['agency_id', 'key', 'value'];

    protected function casts(): array
    {
        return ['value' => 'json'];
    }

    public static function get(string $key, ?string $agencyId = null, mixed $default = null): mixed
    {
        $setting = static::where('key', $key)->where('agency_id', $agencyId)->first();

        return $setting ? $setting->value : $default;
    }

    public static function put(string $key, mixed $value, ?string $agencyId = null): self
    {
        return static::updateOrCreate(['key' => $key, 'agency_id' => $agencyId], ['value' => $value]);
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }
}
