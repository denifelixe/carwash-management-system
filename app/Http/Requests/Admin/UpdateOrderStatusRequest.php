<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrderStatusRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user('admin')?->can('admin.orders.update') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(['booking', 'menunggu', 'proses', 'pelunasan', 'batal'])],
            'reason' => ['exclude_unless:status,batal', 'required', 'string', 'max:2000'],
            'photos' => ['exclude_unless:status,batal', 'sometimes', 'array', 'max:10'],
            'photos.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:20480'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'reason.required' => 'Alasan pembatalan wajib diisi.',
            'reason.max' => 'Alasan pembatalan maksimal 2.000 karakter.',
            'photos.max' => 'Maksimal 10 foto untuk satu pembatalan.',
            'photos.*.image' => 'Lampiran harus berupa foto.',
            'photos.*.mimes' => 'Foto harus berformat JPG, PNG, atau WebP.',
            'photos.*.max' => 'Ukuran setiap foto maksimal 20 MB.',
        ];
    }
}
