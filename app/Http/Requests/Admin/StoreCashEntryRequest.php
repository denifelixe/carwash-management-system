<?php

namespace App\Http\Requests\Admin;

use App\Support\Admin\FinanceCategories;
use App\Support\Admin\OrderQueries;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCashEntryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user('admin')?->can('admin.finance.create') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'direction' => ['required', Rule::in(['in', 'out'])],
            'category' => [
                'required',
                'string',
                Rule::in(FinanceCategories::recordable($this->input('direction'))),
            ],
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'integer', 'min:1', 'max:999999999'],
            'method' => ['required', Rule::in(OrderQueries::PAYMENT_METHODS)],
            /* Outgoing money must carry supporting documentation (BR-10). */
            'attachments' => [
                Rule::requiredIf(fn (): bool => $this->input('direction') === 'out'),
                'array',
                'max:10',
            ],
            'attachments.*' => [
                'file',
                'mimes:jpg,jpeg,png,pdf',
                'max:4096',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'attachments.required' => 'Pengeluaran wajib menyertakan bukti pendukung.',
        ];
    }
}
