<?php

namespace App\Models;

use App\Enums\RoleName;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    protected $fillable = ['slug', 'name', 'description'];

    protected function casts(): array
    {
        return ['is_system' => 'boolean'];
    }

    public static function findBySlug(RoleName|string $slug): self
    {
        $slug = $slug instanceof RoleName ? $slug->value : $slug;

        return static::where('slug', $slug)->firstOrFail();
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
