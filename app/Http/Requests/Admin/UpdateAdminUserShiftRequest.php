<?php

namespace App\Http\Requests\Admin;

use App\Models\AdminShift;
use App\Support\Admin\TransactionShiftResolver;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAdminUserShiftRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user('admin')?->can('admin.users_and_roles.update') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'shift_mode' => ['required', Rule::in([
                TransactionShiftResolver::MODE_FIXED,
                TransactionShiftResolver::MODE_SCHEDULE,
            ])],
            'shift_id' => [
                'nullable',
                Rule::prohibitedIf(fn (): bool => $this->input('shift_mode') === TransactionShiftResolver::MODE_SCHEDULE),
                Rule::exists(AdminShift::class, 'id')->where('is_active', true),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('shift_mode')) {
            $this->merge(['shift_mode' => TransactionShiftResolver::MODE_FIXED]);
        }
    }
}
