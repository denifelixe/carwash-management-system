<?php

namespace App\Http\Requests\Admin;

use App\Models\AdminModule;
use App\Models\AdminRole;
use App\Support\Admin\RoleIcons;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAdminRoleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->route('adminRole') instanceof AdminRole
            && ($this->user('admin')?->can('admin.users_and_roles.update') ?? false);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var AdminRole $adminRole */
        $adminRole = $this->route('adminRole');

        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('admin_roles', 'name')->ignore($adminRole)],
            'description' => ['nullable', 'string', 'max:500'],
            'icon' => ['required', 'string', Rule::in(RoleIcons::values())],
            'is_active' => ['required', 'boolean'],
            'permissions' => ['required', 'array'],
            'permissions.*.module_id' => ['required', 'integer', 'distinct', Rule::exists(AdminModule::class, 'id')->where('is_active', true)],
            'permissions.*.can_create' => ['required', 'boolean'],
            'permissions.*.can_read' => ['required', 'boolean'],
            'permissions.*.can_update' => ['required', 'boolean'],
            'permissions.*.can_delete' => ['required', 'boolean'],
        ];
    }
}
