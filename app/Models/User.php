<?php

namespace App\Models;

use App\Enums\RoleName;
use App\Exceptions\UptimeException;
use App\Notifications\ResetPasswordNotification;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * agency_id et role_id ne sont jamais assignables en masse
     * pour empêcher l'élévation de privilèges via un formulaire.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $with = ['role'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // Administrateur général ⇔ aucune agence.
        static::saving(function (User $user) {
            $isSuperAdmin = Role::whereKey($user->role_id)->value('slug') === RoleName::SuperAdmin->value;

            if ($isSuperAdmin && $user->agency_id !== null) {
                throw new UptimeException('Un administrateur général ne peut pas appartenir à une agence.', logMessage: 'Incohérence rôle/agence');
            }

            if (! $isSuperAdmin && $user->agency_id === null) {
                throw new UptimeException('Cet utilisateur doit appartenir à une agence.', logMessage: 'Utilisateur sans agence');
            }
        });
    }

    /** Emails stockés en minuscules : unicité et connexion insensibles à la casse sur tous les SGBD. */
    protected function email(): Attribute
    {
        return Attribute::set(fn (?string $value) => $value === null ? null : mb_strtolower(trim($value)));
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function assignRole(RoleName $role): static
    {
        $this->role()->associate(Role::findBySlug($role));

        return $this;
    }

    public function hasRole(RoleName $role): bool
    {
        return $this->role?->slug === $role->value;
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole(RoleName::SuperAdmin);
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }

    /** Rôle d'agence (jamais super administrateur). */
    public function isAgencyAdmin(): bool
    {
        return $this->hasRole(RoleName::Admin);
    }

    public function hasPermission(string $permission): bool
    {
        return (bool) $this->role?->loadMissing('permissions')->permissions->contains('slug', $permission);
    }
}
