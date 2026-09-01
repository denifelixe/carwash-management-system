<?php

namespace App\Http\Requests\Admin;

use App\Models\Service;
use App\Support\Admin\ServiceIcons;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateServiceRequest extends FormRequest
{
    use ValidatesServiceVariations;

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
        /** @var Service $service */
        $service = $this->route('service');

        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('services', 'name')->ignore($service)],
            'category' => ['required', 'string', 'max:100'],
            'stamps' => ['required', 'integer', 'min:0', 'max:999'],
            'icon' => ['required', 'string', Rule::in(ServiceIcons::values())],
            'description' => ['nullable', 'string', 'max:500'],
            'is_popular' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
            ...$this->serviceVariationRules(),
        ];
    }

    /** @return list<callable(Validator): void> */
    public function after(): array
    {
        /** @var Service $service */
        $service = $this->route('service');

        return $this->serviceVariationAfter($service);
    }
}
