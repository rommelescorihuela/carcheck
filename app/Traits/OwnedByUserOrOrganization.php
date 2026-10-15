<?php

namespace App\Traits;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Propiedad de un recurso por un usuario (uso personal) o por una organización
 * (cuyos miembros comparten el recurso). El modelo debe tener `owner_type` y
 * `owner_id`.
 */
trait OwnedByUserOrOrganization
{
    /**
     * @return MorphTo<User|Organization, $this>
     */
    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Restringe la consulta a los recursos accesibles por el usuario: los suyos
     * propios o los de las organizaciones a las que pertenece.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeAccessibleBy(Builder $query, User $user, ?int $organizationId = null): Builder
    {
        $organizationIds = $user->organizations()->pluck('organizations.id');

        if ($organizationId !== null) {
            $organizationIds = $organizationIds->filter(
                fn ($id) => (int) $id === $organizationId
            );
        }

        return $query->where(function (Builder $query) use ($user, $organizationIds) {
            $query->where(function (Builder $query) use ($user) {
                $query->where('owner_type', $user->getMorphClass())
                    ->where('owner_id', $user->getKey());
            })->orWhere(function (Builder $query) use ($organizationIds) {
                $query->where('owner_type', (new Organization)->getMorphClass())
                    ->whereIn('owner_id', $organizationIds);
            });
        });
    }

    public function isAccessibleBy(User $user, ?int $organizationId = null): bool
    {
        if ($this->owner_type === $user->getMorphClass()
            && (int) $this->owner_id === $user->getKey()) {
            return true;
        }

        if ($this->owner_type !== (new Organization)->getMorphClass()) {
            return false;
        }

        if ($organizationId !== null && (int) $this->owner_id !== $organizationId) {
            return false;
        }

        return $user->organizations()->whereKey($this->owner_id)->exists();
    }
}
