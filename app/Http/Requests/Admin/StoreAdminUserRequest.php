<?php

namespace App\Http\Requests\Admin;

use App\Models\AdminRole;
use App\Models\AdminShift;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAdminUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user('admin')?->can('admin.users_and_roles.create') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('admins', 'email')],
            'phone' => ['nullable', 'string', 'max:30', Rule::unique('admins', 'phone')],
            'role_id' => ['required', Rule::exists(AdminRole::class, 'id')->where('is_active', true)],
            'shift_id' => ['nullable', Rule::exists(AdminShift::class, 'id')->where('is_active', true)],
            'password' => ['required', 'string', 'confirmed'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
