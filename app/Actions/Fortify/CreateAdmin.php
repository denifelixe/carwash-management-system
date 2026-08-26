<?php

namespace App\Actions\Fortify;

use App\Concerns\AdminProfileValidationRules;
use App\Concerns\PasswordValidationRules;
use App\Models\Admin;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateAdmin implements CreatesNewUsers
{
    use AdminProfileValidationRules, PasswordValidationRules;

    /**
     * Validate and create a newly registered admin.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): Admin
    {
        Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
        ])->validate();

        return Admin::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => $input['password'],
        ]);
    }
}
