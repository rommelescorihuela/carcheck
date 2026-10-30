<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use NotificationChannels\WebPush\HasPushSubscriptions;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'currency'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasPushSubscriptions, HasRoles, Notifiable, SoftDeletes;

    /**
     * Solo el personal de plataforma entra al panel `/admin`.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->hasRole('super_admin');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Organizaciones a las que pertenece el usuario.
     *
     * @return BelongsToMany<Organization, $this>
     */
    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class)
            ->withPivot(['role', 'joined_at'])
            ->withTimestamps();
    }

    /**
     * Organizaciones de las que el usuario es propietario.
     *
     * @return HasMany<Organization, $this>
     */
    public function ownedOrganizations(): HasMany
    {
        return $this->hasMany(Organization::class, 'owner_id');
    }

    /**
     * Invitaciones pendientes dirigidas al email del usuario.
     *
     * @return HasMany<Invitation, $this>
     */
    public function invitations(): HasMany
    {
        return $this->hasMany(Invitation::class, 'email', 'email');
    }

    /**
     * Preferencias de aviso por categoría.
     *
     * @return HasMany<NotificationPreference, $this>
     */
    public function notificationPreferences(): HasMany
    {
        return $this->hasMany(NotificationPreference::class);
    }

    /**
     * Vehículos del ámbito personal (los de sus organizaciones no cuentan aquí).
     *
     * @return MorphMany<Vehicle, $this>
     */
    public function vehicles(): MorphMany
    {
        return $this->morphMany(Vehicle::class, 'owner');
    }

    /**
     * Suscripciones del propietario (histórico completo).
     *
     * @return MorphMany<Subscription, $this>
     */
    public function subscriptions(): MorphMany
    {
        return $this->morphMany(Subscription::class, 'owner');
    }

    /**
     * Suscripción vigente para el panel: la última por id.
     *
     * @return MorphOne<Subscription, $this>
     */
    public function currentSubscription(): MorphOne
    {
        return $this->morphOne(Subscription::class, 'owner')->latestOfMany();
    }

    public function belongsToOrganization(Organization $organization): bool
    {
        return $this->organizations()->whereKey($organization->getKey())->exists();
    }

    public function roleIn(Organization $organization): ?string
    {
        return $this->organizations()
            ->whereKey($organization->getKey())
            ->first()?->pivot->role;
    }
}
