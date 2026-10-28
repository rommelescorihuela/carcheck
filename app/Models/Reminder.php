<?php

namespace App\Models;

use App\Enums\ReminderKind;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable([
    'kind', 'source_type', 'source_id', 'fingerprint', 'title', 'body',
    'due_on', 'due_km', 'notified_at', 'overdue_notified_at',
])]
class Reminder extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => ReminderKind::class,
            'due_on' => 'date',
            'due_km' => 'integer',
            'notified_at' => 'datetime',
            'overdue_notified_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Vehicle, $this>
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return HasMany<ReminderDismissal, $this>
     */
    public function dismissals(): HasMany
    {
        return $this->hasMany(ReminderDismissal::class);
    }

    public function daysLeft(): ?int
    {
        if ($this->due_on === null) {
            return null;
        }

        return (int) now()->startOfDay()->diffInDays($this->due_on->copy()->startOfDay(), false);
    }

    public function kmLeft(?Vehicle $vehicle = null): ?int
    {
        if ($this->due_km === null) {
            return null;
        }

        $vehicle ??= $this->vehicle;

        return $this->due_km - (int) $vehicle->odometer_km;
    }

    public function isOverdue(?Vehicle $vehicle = null): bool
    {
        $days = $this->daysLeft();
        $km = $this->kmLeft($vehicle);

        return ($days !== null && $days < 0) || ($km !== null && $km <= 0);
    }

    public function status(?Vehicle $vehicle = null): string
    {
        return $this->isOverdue($vehicle) ? 'overdue' : 'upcoming';
    }
}
