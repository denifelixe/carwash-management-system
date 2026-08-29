<?php

namespace App\Http\Requests\Admin;

use App\Models\AdminWorkShift;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWorkShiftRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user('admin')?->can('admin.master_work_shifts.update') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var AdminWorkShift $workShift */
        $workShift = $this->route('workShift');

        return [
            'key' => ['required', 'string', 'alpha_dash:ascii', 'max:50', Rule::unique('admin_work_shifts', 'key')->ignore($workShift)],
            'name' => ['required', 'string', 'max:100', Rule::unique('admin_work_shifts', 'name')->ignore($workShift)],
            'starts_at' => ['nullable', 'required_with:ends_at', 'date_format:H:i', 'different:ends_at'],
            'ends_at' => ['nullable', 'required_with:starts_at', 'date_format:H:i', 'different:starts_at'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
