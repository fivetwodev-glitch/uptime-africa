<?php

namespace App\Models;

use App\Models\Concerns\BelongsToAgency;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Client extends Model
{
    use BelongsToAgency, HasFactory, HasUlids;

    protected $fillable = ['name', 'contact_name', 'email', 'phone', 'notes'];

    public function sites(): HasMany
    {
        return $this->hasMany(Site::class);
    }
}
