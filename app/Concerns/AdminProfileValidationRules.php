<?php

namespace App\Concerns;

use App\Models\Admin;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait AdminProfileValidationRules
{
    /**
     * Get the validation rules used to validate admin profiles.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function profileRules(?int $adminId = null): array
    {
        return [
            'name' => $this->nameRules(),
            'email' => $this->emailRules($adminId),
        ];
    }

    /**
     * Get the validation rules used to validate admin names.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function nameRules(): array
    {
        return ['required', 'string', 'max:255'];
    }

    /**
     * Get the validation rules used to validate admin emails.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function emailRules(?int $adminId = null): array
    {
        return [
            'required',
            'string',
            'email',
            'max:255',
            $adminId === null
                ? Rule::unique(Admin::class)
                : Rule::unique(Admin::class)->ignore($adminId),
        ];
    }
}
