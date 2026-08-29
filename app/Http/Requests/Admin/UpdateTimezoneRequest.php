<?php

namespace App\Http\Requests\Admin;

use App\Support\Timezones;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTimezoneRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('admin')?->can('admin.master_timezone.update') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'timezone' => ['required', 'string', Rule::in(array_keys(Timezones::ZONES))],
        ];
    }
}
