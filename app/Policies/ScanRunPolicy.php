<?php

namespace App\Policies;

use App\Models\ScanRun;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ScanRunPolicy
{
    public function view(User $user, ScanRun $scanRun): Response
    {
        return $user->getKey() === $scanRun->user_id
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
