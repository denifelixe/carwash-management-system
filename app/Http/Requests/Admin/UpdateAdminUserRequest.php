<?php

namespace App\Http\Requests\Admin;

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\AdminWorkShift;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateAdminUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $adminUser = $this->route('adminUser');

        return $adminUser instanceof Admin
            && ! $adminUser->is_owner
            && ($this->user('admin')?->can('admin.users_and_roles.update') ?? false);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Admin $adminUser */
        $adminUser = $this->route('adminUser');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('admins', 'email')->ignore($adminUser)],
            'phone' => ['nullable', 'string', 'max:30', Rule::unique('admins', 'phone')->ignore($adminUser)],
            'role_id' => ['required', Rule::exists(AdminRole::class, 'id')->where('is_active', true)],
            'work_shift_id' => ['nullable', Rule::exists(AdminWorkShift::class, 'id')->where('is_active', true)],
            'password' => ['nullable', 'confirmed', Password::defaults()],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
