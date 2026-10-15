<?php

namespace App\Policies;

use App\Models\Organization;
use App\Models\User;

class OrganizationPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Organization $organization): bool
    {
        return $organization->hasMember($user);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Organization $organization): bool
    {
        return in_array($organization->roleOf($user), ['owner', 'admin'], true);
    }

    public function delete(User $user, Organization $organization): bool
    {
        return $organization->roleOf($user) === 'owner';
    }

    public function manageMembers(User $user, Organization $organization): bool
    {
        return in_array($organization->roleOf($user), ['owner', 'admin'], true);
    }

    public function changeRole(User $user, Organization $organization): bool
    {
        return $organization->roleOf($user) === 'owner';
    }
}
