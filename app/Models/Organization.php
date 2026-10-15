<?php

namespace App\Models;

use Database\Factories\OrganizationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
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

    public function hasMember(User $user): bool
    {
        return $this->roleOf($user) !== null;
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
