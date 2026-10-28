<?php

namespace App\Models;

use App\Enums\PlanInterval;
use Database\Factories\PlanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'code', 'name', 'description', 'price', 'currency', 'interval', 'trial_days',
    'max_vehicles', 'max_members', 'is_active', 'sort',
])]
class Plan extends Model
{
    /** @use HasFactory<PlanFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'interval' => PlanInterval::class,
            'trial_days' => 'integer',
            'max_vehicles' => 'integer',
            'max_members' => 'integer',
            'is_active' => 'boolean',
            'sort' => 'integer',
        ];
    }

    /**
     * @return HasMany<Subscription, $this>
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }
}
