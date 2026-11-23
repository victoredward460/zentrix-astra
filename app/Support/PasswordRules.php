<?php

namespace App\Support;

use Illuminate\Validation\Rules\Password;

final class PasswordRules
{
    public static function strong(): Password
    {
        return Password::min(12)->mixedCase()->numbers()->symbols();
    }
}
