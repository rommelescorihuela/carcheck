<?php

namespace App\Models;

use App\Enums\FuelType;
use App\Enums\VehicleType;
use App\Traits\OwnedByUserOrOrganization;
use Database\Factories\VehicleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

#[Fillable(['make', 'model', 'year', 'plate', 'vin', 'type', 'fuel_type', 'odometer_km', 'color', 'notes'])]
class Vehicle extends Model implements HasMedia
{
    /** @use HasFactory<VehicleFactory> */
    use HasFactory, InteractsWithMedia, OwnedByUserOrOrganization, SoftDeletes;

    /**
     * Valores por defecto a nivel de modelo (coinciden con los defaults de la BD).
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'type' => 'car',
        'fuel_type' => 'gasoline',
        'odometer_km' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => VehicleType::class,
            'fuel_type' => FuelType::class,
            'year' => 'integer',
            'odometer_km' => 'integer',
        ];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('photo')->singleFile();
    }

    /**
     * @return HasMany<MaintenanceRecord, $this>
     */
    public function maintenanceRecords(): HasMany
    {
        return $this->hasMany(MaintenanceRecord::class);
    }

    /**
     * @return HasMany<MaintenancePlan, $this>
     */
    public function maintenancePlans(): HasMany
    {
        return $this->hasMany(MaintenancePlan::class);
    }

    /**
     * @return HasMany<FuelEntry, $this>
     */
    public function fuelEntries(): HasMany
    {
        return $this->hasMany(FuelEntry::class);
    }

    /**
     * @return Attribute<string|null, never>
     */
    protected function photoUrl(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->getFirstMediaUrl('photo') ?: null);
    }
}
