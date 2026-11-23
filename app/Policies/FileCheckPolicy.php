<?php

namespace App\Policies;

use App\Models\FileCheck;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class FileCheckPolicy
{
    public function view(User $user, FileCheck $fileCheck): Response
    {
        return $user->getKey() === $fileCheck->user_id
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    public function delete(User $user, FileCheck $fileCheck): Response
    {
        return $user->getKey() === $fileCheck->user_id
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
