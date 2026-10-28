<?php

namespace App\Services;

use App\Enums\NotificationCategory;
use App\Models\User;

/**
 * Preferencias de aviso por usuario y categoría. Si no hay fila guardada, todos los canales están
 * activos: el silencio por defecto sería peor que un aviso de más.
 */
class NotificationPreferences
{
    /**
     * @return array<string, array{category: string, category_label: string, in_app: bool, email: bool, push: bool}>
     */
    public function defaults(): array
    {
        $defaults = [];

        foreach (NotificationCategory::cases() as $category) {
            $defaults[$category->value] = [
                'category' => $category->value,
                'category_label' => $category->label(),
                'in_app' => true,
                'email' => true,
                'push' => true,
            ];
        }

        return $defaults;
    }

    /**
     * @return array<int, array{category: string, category_label: string, in_app: bool, email: bool, push: bool}>
     */
    public function for(User $user): array
    {
        $preferences = $this->defaults();

        foreach ($user->notificationPreferences()->get() as $row) {
            if (! isset($preferences[$row->category])) {
                continue;
            }

            $preferences[$row->category] = [
                'category' => $row->category,
                'category_label' => NotificationCategory::from($row->category)->label(),
                'in_app' => $row->in_app,
                'email' => $row->email,
                'push' => $row->push,
            ];
        }

        return array_values($preferences);
    }

    public function allows(User $user, NotificationCategory $category, string $channel): bool
    {
        $row = $user->notificationPreferences()
            ->where('category', $category->value)
            ->first();

        if ($row === null) {
            return true;
        }

        return (bool) $row->{$channel};
    }

    /**
     * @param  array<int, array{category: string, in_app: bool, email: bool, push: bool}>  $preferences
     */
    public function update(User $user, array $preferences): void
    {
        foreach ($preferences as $preference) {
            $user->notificationPreferences()->updateOrCreate(
                ['category' => $preference['category']],
                [
                    'in_app' => (bool) $preference['in_app'],
                    'email' => (bool) $preference['email'],
                    'push' => (bool) $preference['push'],
                ],
            );
        }
    }
}
