<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Models\Admin;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\ResetsUserPasswords;

class ResetAdminPassword implements ResetsUserPasswords
{
    use PasswordValidationRules;

    /**
     * Validate and reset the admin's forgotten password.
     *
     * @param  array<string, string>  $input
     */
    public function reset(Admin $admin, array $input): void
    {
        Validator::make($input, [
            'password' => $this->passwordRules(),
        ])->validate();

        $admin->forceFill([
            'password' => $input['password'],
        ])->save();
    }
}
