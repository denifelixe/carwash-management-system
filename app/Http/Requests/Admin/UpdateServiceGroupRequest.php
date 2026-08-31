<?php

namespace App\Http\Requests\Admin;

use App\Models\ServiceGroup;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateServiceGroupRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user('admin')?->can('admin.master_services.update') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var ServiceGroup $serviceGroup */
        $serviceGroup = $this->route('serviceGroup');

        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('service_groups', 'name')->ignore($serviceGroup)],
        ];
    }
}
