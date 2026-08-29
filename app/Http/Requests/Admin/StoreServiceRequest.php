<?php

namespace App\Http\Requests\Admin;

use App\Support\Admin\ServiceIcons;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreServiceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user('admin')?->can('admin.master_services.create') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('services', 'name')],
            'category' => ['required', 'string', 'max:100'],
            'price' => ['required', 'integer', 'min:0', 'max:999999999'],
            'stamps' => ['required', 'integer', 'min:0', 'max:999'],
            'icon' => ['required', 'string', Rule::in(ServiceIcons::values())],
            'description' => ['nullable', 'string', 'max:500'],
            'is_popular' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
