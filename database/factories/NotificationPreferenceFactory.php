<?php

namespace Database\Factories;

use App\Enums\NotificationCategory;
use App\Models\NotificationPreference;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NotificationPreference>
 */
class NotificationPreferenceFactory extends Factory
{
    protected $model = NotificationPreference::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'category' => NotificationCategory::System->value,
            'in_app' => true,
            'email' => true,
            'push' => true,
        ];
    }

    public function forCategory(NotificationCategory $category): static
    {
        return $this->state(fn (): array => ['category' => $category->value]);
    }
}
