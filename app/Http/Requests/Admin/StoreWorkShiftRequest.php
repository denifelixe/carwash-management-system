<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWorkShiftRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user('admin')?->can('admin.master_work_shifts.create') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'key' => ['required', 'string', 'alpha_dash:ascii', 'max:50', Rule::unique('admin_work_shifts', 'key')],
            'name' => ['required', 'string', 'max:100', Rule::unique('admin_work_shifts', 'name')],
            'starts_at' => ['required', 'date_format:H:i', 'different:ends_at'],
            'ends_at' => ['required', 'date_format:H:i', 'different:starts_at'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
