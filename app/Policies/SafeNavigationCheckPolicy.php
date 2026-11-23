<?php

namespace App\Policies;

use App\Models\SafeNavigationCheck;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class SafeNavigationCheckPolicy
{
    public function view(User $user, SafeNavigationCheck $check): Response
    {
        return $user->isAdministrator() && $user->getKey() === $check->user_id
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
