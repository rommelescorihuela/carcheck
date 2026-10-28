<?php

namespace App\Models;

use Database\Factories\MaintenanceTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable(['name', 'slug', 'interval_km', 'interval_months'])]
class MaintenanceType extends Model
{
    /** @use HasFactory<MaintenanceTypeFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'interval_km' => 'integer',
            'interval_months' => 'integer',
        ];
    }

    /**
     * @return MorphTo<User|Organization, $this>
     */
    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    public function isGlobal(): bool
    {
        return $this->owner_type === null;
    }

    /**
     * @return HasMany<MaintenancePlan, $this>
     */
    public function plans(): HasMany
    {
        return $this->hasMany(MaintenancePlan::class);
    }

    /**
     * @return HasMany<MaintenanceRecord, $this>
     */
    public function records(): HasMany
    {
        return $this->hasMany(MaintenanceRecord::class);
    }

    /**
     * Tipos visibles para el usuario: los globales + los de su ámbito.
     *
     * @param  Builder<MaintenanceType>  $query
     * @return Builder<MaintenanceType>
     */
    public function scopeAvailableTo(Builder $query, User $user): Builder
    {
        $organizationIds = $user->organizations()->pluck('organizations.id');

        return $query->where(function (Builder $query) use ($user, $organizationIds) {
            $query->whereNull('owner_type')
                ->orWhere(function (Builder $query) use ($user) {
                    $query->where('owner_type', $user->getMorphClass())
                        ->where('owner_id', $user->getKey());
                })
                ->orWhere(function (Builder $query) use ($organizationIds) {
                    $query->where('owner_type', (new Organization)->getMorphClass())
                        ->whereIn('owner_id', $organizationIds);
                });
        });
    }
}
