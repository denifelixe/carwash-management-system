<?php

namespace App\Http\Requests\Admin;

use App\Actions\Admin\RecordStockMovement;
use App\Models\StockItem;
use App\Support\Admin\StockQueries;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreStockMovementRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user('admin')?->can('admin.inventory.create') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * Ins and outs are given as plain amounts and get their sign from the type.
     * A correction is the one kind that arrives signed, because it exists to
     * write off breakage as readily as to add back a miscount.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', 'string', Rule::in(StockQueries::MOVEMENT_TYPES)],
            'quantity' => $this->input('type') === 'penyesuaian'
                ? ['required', 'integer', 'not_in:0', 'min:-1000000', 'max:1000000']
                : ['required', 'integer', 'min:1', 'max:1000000'],
            'unit_cost' => ['nullable', 'integer', 'min:0', 'max:1000000000'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * On hand may never go negative, so the movement is measured against the
     * item before it is written. The demo clamps to zero instead; a live till
     * has to tell the operator what is actually left.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $item = $this->stockItem();

            if (! $item->is_active) {
                $validator->errors()->add('type', 'Item nonaktif tidak bisa menerima pergerakan stok.');

                return;
            }

            $delta = RecordStockMovement::delta(
                (string) $this->input('type'),
                $this->integer('quantity'),
            );

            if ($item->quantity + $delta < 0) {
                $validator->errors()->add('quantity', sprintf(
                    'Stok %s hanya tersisa %d %s.',
                    $item->name,
                    $item->quantity,
                    $item->unit,
                ));
            }
        }];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'type.in' => 'Jenis pergerakan tidak dikenal.',
            'quantity.required' => 'Jumlah wajib diisi.',
            'quantity.min' => 'Jumlah minimal 1.',
            'quantity.not_in' => 'Penyesuaian tidak boleh nol.',
        ];
    }

    public function stockItem(): StockItem
    {
        /** @var StockItem $item */
        $item = $this->route('stockItem');

        return $item;
    }

    /**
     * @return array{type: string, quantity: int, unit_cost: int|null, note: string|null}
     */
    public function movement(): array
    {
        /** @var array{type: string, quantity: int, unit_cost?: int|null, note?: string|null} $data */
        $data = $this->validated();

        return [
            'type' => $data['type'],
            'quantity' => $data['quantity'],
            'unit_cost' => $data['unit_cost'] ?? null,
            'note' => $data['note'] ?? null,
        ];
    }

    protected function prepareForValidation(): void
    {
        $note = trim((string) $this->input('note', ''));

        $this->merge([
            'note' => $note === '' ? null : $note,
        ]);
    }
}
