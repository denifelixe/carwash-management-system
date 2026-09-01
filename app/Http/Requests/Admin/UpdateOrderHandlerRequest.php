<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateOrderHandlerRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user('admin')?->can('admin.orders.update') ?? false;
    }

    /**
     * Normalize the manually entered name while preserving a cleared handler.
     */
    protected function prepareForValidation(): void
    {
        $handledBy = Str::squish((string) $this->input('handled_by'));
        $handledByAdminId = $this->input('handled_by_admin_id');

        $this->merge([
            'handled_by' => $handledBy !== '' ? $handledBy : null,
            'handled_by_admin_id' => filled($handledByAdminId) ? (int) $handledByAdminId : null,
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'handled_by_admin_id' => [
                'nullable',
                'integer',
                Rule::in([(int) $this->user('admin')?->getAuthIdentifier()]),
            ],
            'handled_by' => ['nullable', 'string', 'max:255'],
        ];
    }
}
