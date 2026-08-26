<?php

namespace App\Http\Requests\Settings;

use App\Concerns\AdminProfileValidationRules;
use App\Models\Admin;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ProfileUpdateRequest extends FormRequest
{
    use AdminProfileValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $admin = $this->user('admin');

        abort_unless($admin instanceof Admin, 403);

        return $this->profileRules($admin->id);
    }
}
