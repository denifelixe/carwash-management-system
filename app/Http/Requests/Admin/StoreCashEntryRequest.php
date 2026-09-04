<?php

namespace App\Http\Requests\Admin;

use App\Models\AdminShift;
use App\Support\Admin\AdminModuleActions;
use App\Support\Admin\FinanceCategories;
use App\Support\Admin\OrderQueries;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
        $canManageOccurrence = $this->canManageOccurrence();

        return [
            'entry_date' => [
                Rule::excludeIf(! $canManageOccurrence),
                'required',
                'date_format:Y-m-d',
                'before_or_equal:today',
            ],
            'entry_time' => [Rule::excludeIf(! $canManageOccurrence), 'required', 'date_format:H:i'],
            'direction' => ['required', Rule::in(['in', 'out'])],
            'category' => [
                'required',
                'string',
                Rule::in(FinanceCategories::recordable($this->input('direction'))),
            ],
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'integer', 'min:1', 'max:999999999'],
            'method' => ['required', Rule::in(OrderQueries::recordableMethods($this->input('direction')))],
            'transaction_shift_id' => [
                'nullable',
                'integer',
                Rule::exists(AdminShift::class, 'id')->where('is_active', true),
            ],
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

    /** @return list<callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty() || ! $this->canManageOccurrence()) {
                return;
            }

            $occurredAt = CarbonImmutable::createFromFormat(
                '!Y-m-d H:i',
                $this->string('entry_date').' '.$this->string('entry_time'),
            );

            if ($occurredAt->isAfter(now())) {
                $validator->errors()->add('entry_time', 'Waktu transaksi tidak boleh melewati waktu sekarang.');
            }
        }];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'entry_date.before_or_equal' => 'Tanggal transaksi tidak boleh melewati hari ini.',
            'entry_time.required' => 'Waktu transaksi wajib diisi.',
            'entry_time.date_format' => 'Format waktu transaksi tidak valid.',
            'attachments.required' => 'Pengeluaran wajib menyertakan bukti pendukung.',
        ];
    }

    private function canManageOccurrence(): bool
    {
        return $this->user('admin')?->can(
            'admin.finance.'.AdminModuleActions::EDIT_CASH_ENTRY_BACKDATE,
        ) ?? false;
    }
}
