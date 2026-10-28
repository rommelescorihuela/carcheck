<?php

namespace App\Policies;

use App\Models\MaintenanceType;
use App\Models\Organization;
use App\Models\User;

class MaintenanceTypePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, MaintenanceType $type): bool
    {
        if ($type->isGlobal()) {
            return true;
        }

        if ($type->owner_type === $user->getMorphClass()) {
            return (int) $type->owner_id === $user->getKey();
        }

        if ($type->owner_type === (new Organization)->getMorphClass()) {
            return $user->organizations()->whereKey($type->owner_id)->exists();
        }

        return false;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, MaintenanceType $type): bool
    {
        return $this->manage($user, $type);
    }

    public function delete(User $user, MaintenanceType $type): bool
    {
        return $this->manage($user, $type);
    }

    private function manage(User $user, MaintenanceType $type): bool
    {
        if ($type->isGlobal()) {
            return false;
        }

        if ($type->owner_type === $user->getMorphClass()) {
            return (int) $type->owner_id === $user->getKey();
        }

        if ($type->owner_type === (new Organization)->getMorphClass()) {
            /** @var Organization $organization */
            $organization = $type->owner;

            return in_array($organization->roleOf($user), ['owner', 'admin'], true);
        }

        return false;
    }
}
