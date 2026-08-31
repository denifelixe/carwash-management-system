<?php

namespace App\Http\Requests\Admin;

use App\Models\Service;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReorderServicesRequest extends FormRequest
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
        return [
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'distinct', Rule::exists('services', 'id')],
        ];
    }

    /**
     * A partial list would renumber only some of the catalog, so a stale page
     * (a service added in another tab) has to be reloaded before it can save.
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->hasAny(['ids', 'ids.*'])) {
                    return;
                }

                if (count($this->input('ids', [])) !== Service::query()->count()) {
                    $validator->errors()->add(
                        'ids',
                        'Daftar urutan tidak lengkap, muat ulang halaman lalu coba lagi.',
                    );
                }
            },
        ];
    }
}
