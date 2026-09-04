<?php

namespace App\Http\Requests\Admin;

use App\Models\CashEntry;
use App\Support\Admin\AdminModuleActions;
use App\Support\Admin\FinanceCategories;
use App\Support\Admin\OperationalDataWindow;
use App\Support\Admin\OrderQueries;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
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
            'entry_date' => [
                'required',
                'date_format:Y-m-d',
                'after_or_equal:'.OperationalDataWindow::cutoff()->toDateString(),
                'before_or_equal:today',
            ],
            'category' => [
                'required',
                'string',
                Rule::in(FinanceCategories::recordable($this->entry()->direction)),
            ],
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'integer', 'min:1', 'max:999999999'],
            'method' => ['required', Rule::in(OrderQueries::recordableMethods($this->entry()->direction))],
            'attachments' => [
                'nullable',
                'array',
                'max:10',
            ],
            'attachments.*' => [
                'file',
                'mimes:jpg,jpeg,png,pdf',
                'max:4096',
            ],
            'removed_attachment_ids' => ['nullable', 'array', 'max:10'],
            'removed_attachment_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('cash_entry_attachments', 'id')
                    ->where('cash_entry_id', $this->entry()->id),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'entry_date.required' => 'Tanggal transaksi wajib diisi.',
            'entry_date.date_format' => 'Format tanggal transaksi tidak valid.',
            'entry_date.after_or_equal' => 'Tanggal transaksi tidak dapat dipindahkan lebih dari 30 hari ke belakang.',
            'entry_date.before_or_equal' => 'Tanggal transaksi tidak boleh melewati hari ini.',
            'attachments.required' => 'Pengeluaran wajib menyertakan bukti pendukung.',
        ];
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $entryDateChanged = $this->string('entry_date')->toString()
                !== $this->entry()->entry_date->toDateString();
            $canEditBackdate = $this->user('admin')?->can(
                'admin.finance.'.AdminModuleActions::EDIT_CASH_ENTRY_BACKDATE,
            ) ?? false;

            if ($entryDateChanged && ! $canEditBackdate) {
                $validator->errors()->add(
                    'entry_date',
                    'Role Anda tidak memiliki akses untuk mengubah tanggal transaksi.',
                );

                return;
            }

            $removedCount = count($this->input('removed_attachment_ids', []));
            $existingCount = $this->entry()->attachments()->count() - $removedCount;
            $newCount = count($this->file('attachments', []));
            $resultingCount = $existingCount + $newCount;

            if ($resultingCount > 10) {
                $validator->errors()->add('attachments', 'Maksimal 10 lampiran untuk setiap catatan keuangan.');
            }

            if ($this->entry()->direction === 'out' && $resultingCount === 0) {
                $validator->errors()->add('attachments', 'Pengeluaran wajib menyertakan bukti pendukung.');
            }
        }];
    }

    private function entry(): CashEntry
    {
        /** @var CashEntry $entry */
        $entry = $this->route('cashEntry');

        return $entry;
    }
}
