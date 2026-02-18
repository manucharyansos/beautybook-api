<?php

namespace App\Policies;

use App\Models\User;

class StaffPolicy
{
    public function viewAny(User $actor): bool
    {
        return in_array($actor->role, [User::ROLE_OWNER, User::ROLE_MANAGER, User::ROLE_SUPER_ADMIN], true);
    }

    public function create(User $actor): bool
    {
        return in_array($actor->role, [User::ROLE_OWNER, User::ROLE_MANAGER, User::ROLE_SUPER_ADMIN], true);
    }

    public function deactivate(User $actor, User $target): bool
    {
        // super admin կարող է ամեն ինչ (բայց owner-ին չանջատենք)
        if ($actor->role === User::ROLE_SUPER_ADMIN) {
            return $target->role !== User::ROLE_OWNER;
        }

        // միայն owner/manager
        if (!in_array($actor->role, [User::ROLE_OWNER, User::ROLE_MANAGER], true)) {
            return false;
        }

        // tenant guard
        if ($actor->salon_id !== $target->salon_id) return false;

        // owner-ին չանջատել
        if ($target->role === User::ROLE_OWNER) return false;

        // ինքն իրեն չանջատել
        if ($actor->id === $target->id) return false;

        return true;
    }

    public function activate(User $actor, User $target): bool
    {
        // super admin կարող է
        if ($actor->role === User::ROLE_SUPER_ADMIN) return true;

        if (!in_array($actor->role, [User::ROLE_OWNER, User::ROLE_MANAGER], true)) {
            return false;
        }

        if ($actor->salon_id !== $target->salon_id) return false;

        return true;
    }
}
