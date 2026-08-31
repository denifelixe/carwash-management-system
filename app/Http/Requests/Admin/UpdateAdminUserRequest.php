<?php

namespace App\Http\Requests\Admin;

use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\AdminShift;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'shift_id' => ['nullable', Rule::exists(AdminShift::class, 'id')->where('is_active', true)],
            'password' => ['nullable', 'string', 'confirmed'],
            'is_active' => ['required', 'boolean'],
            'photo' => $this->user('admin')?->is_owner
                ? ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:20480']
                : ['prohibited'],
        ];
    }
}
