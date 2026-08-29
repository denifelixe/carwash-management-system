<?php

namespace App\Http\Requests\Admin;

use App\Models\CashEntry;
use App\Support\Admin\FinanceCategories;
use App\Support\Admin\OrderQueries;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCashEntryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user('admin')?->can('admin.finance.update') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'category' => [
                'required',
                'string',
                Rule::in(FinanceCategories::recordable($this->entry()->direction)),
            ],
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'integer', 'min:1', 'max:999999999'],
            'method' => ['required', Rule::in(OrderQueries::PAYMENT_METHODS)],
            /*
             * Keeping the document already on file counts as satisfying BR-10,
             * so a new upload is only demanded when the entry has none.
             */
            'attachment' => [
                Rule::requiredIf(fn (): bool => $this->entry()->direction === 'out'
                    && $this->entry()->attachment_path === null),
                'nullable',
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
            'attachment.required' => 'Pengeluaran wajib menyertakan bukti pendukung.',
        ];
    }

    private function entry(): CashEntry
    {
        /** @var CashEntry $entry */
        $entry = $this->route('cashEntry');

        return $entry;
    }
}
