<?php

namespace App\Models;

use Database\Factories\OrganizationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Str;

#[Fillable(['name', 'slug', 'owner_id', 'currency', 'timezone'])]
class Organization extends Model
{
    /** @use HasFactory<OrganizationFactory> */
    use HasFactory;

    /**
     * Caché de roles por usuario para la instancia actual (evita repetir la
     * consulta al pivot dentro del mismo request).
     *
     * @var array<int, string|null>
     */
    private array $roleCache = [];

    protected static function booted(): void
    {
        static::creating(function (Organization $organization) {
            if (blank($organization->slug)) {
                $organization->slug = static::uniqueSlug($organization->name);
            }
        });

        // Invariante: el propietario siempre es miembro de su organización,
        // sin importar el punto de entrada (API, panel Filament, seeder).
        static::created(function (Organization $organization) {
            $organization->members()->syncWithoutDetaching([
                $organization->owner_id => ['role' => 'owner', 'joined_at' => now()],
            ]);
        });

        // Cambiar de propietario desde el panel no puede dejar al nuevo fuera de la
        // organización ni al anterior con rol `owner`: el anterior baja a `admin`.
        static::updated(function (Organization $organization) {
            if (! $organization->wasChanged('owner_id')) {
                return;
            }

            $previous = $organization->getOriginal('owner_id');

            $organization->members()->syncWithoutDetaching([
                $organization->owner_id => ['role' => 'owner', 'joined_at' => now()],
            ]);

            if ($previous !== null
                && (int) $previous !== (int) $organization->owner_id
                && $organization->members()->whereKey($previous)->exists()) {
                $organization->members()->updateExistingPivot($previous, ['role' => 'admin']);
            }

            $organization->roleCache = [];
        });

        // Al dar de baja la organización sus vehículos se van con ella (soft delete);
        // las suscripciones se conservan como historial de facturación.
        static::deleting(function (Organization $organization) {
            $organization->vehicles()->delete();
        });
    }

    public static function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'organizacion';
        $slug = $base;
        $suffix = 1;

        while (static::where('slug', $slug)->exists()) {
            $slug = $base.'-'.(++$suffix);
        }

        return $slug;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot(['role', 'joined_at'])
            ->withTimestamps();
    }

    /**
     * @return HasMany<Invitation, $this>
     */
    public function invitations(): HasMany
    {
        return $this->hasMany(Invitation::class);
    }

    /**
     * @return MorphMany<Vehicle, $this>
     */
    public function vehicles(): MorphMany
    {
        return $this->morphMany(Vehicle::class, 'owner');
    }

    /**
     * @return MorphMany<Subscription, $this>
     */
    public function subscriptions(): MorphMany
    {
        return $this->morphMany(Subscription::class, 'owner');
    }

    /**
     * @return MorphOne<Subscription, $this>
     */
    public function currentSubscription(): MorphOne
    {
        return $this->morphOne(Subscription::class, 'owner')->latestOfMany();
    }

    public function hasMember(User $user): bool
    {
        return $this->roleOf($user) !== null;
    }

    /**
     * Gestiona el garaje: vehículos, miembros e invitaciones de `driver`/`member`.
     */
    public function isManager(User $user): bool
    {
        return in_array($this->roleOf($user), ['owner', 'admin'], true);
    }

    /**
     * Puede registrar combustible y mantenimiento. El `driver` existe justo para esto.
     */
    public function canLog(User $user): bool
    {
        return $this->hasMember($user);
    }

    public function roleOf(User $user): ?string
    {
        $key = $user->getKey();

        if (! array_key_exists($key, $this->roleCache)) {
            $this->roleCache[$key] = $this->members()
                ->whereKey($key)
                ->first()?->pivot->role;
        }

        return $this->roleCache[$key];
    }
}
