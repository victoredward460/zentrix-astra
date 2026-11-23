<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->isAdministrator();
    }

    public function view(User $actor, User $target): bool
    {
        return $actor->isAdministrator() || $actor->is($target);
    }

    public function update(User $actor, User $target): bool
    {
        return $actor->isAdministrator() || $actor->is($target);
    }

    public function changeRole(User $actor, User $target): bool
    {
        return $actor->isAdministrator() && ! $actor->is($target);
    }

    public function changeStatus(User $actor, User $target): bool
    {
        return $actor->isAdministrator() && ! $actor->is($target);
    }
}
