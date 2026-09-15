<?php

namespace App\Http\Requests\Admin;

use App\Models\StockItem;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateStockItemRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user('admin')?->can('admin.inventory.update') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * There is deliberately no `quantity` here: on hand only ever moves through
     * a recorded movement, never through the item form.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'sku' => [
                'required',
                'string',
                'max:50',
                Rule::unique(StockItem::class, 'sku')->ignore($this->route('stockItem')),
            ],
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:100'],
            'unit' => ['required', 'string', 'max:32'],
            'min_quantity' => ['required', 'integer', 'min:0', 'max:1000000'],
            'unit_cost' => ['required', 'integer', 'min:0', 'max:1000000000'],
            'supplier' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'sku.required' => 'SKU wajib diisi.',
            'sku.unique' => 'SKU ini sudah dipakai item lain.',
            'name.required' => 'Nama item wajib diisi.',
            'category.required' => 'Kategori wajib diisi.',
            'unit.required' => 'Satuan wajib diisi.',
            'min_quantity.min' => 'Stok minimum tidak boleh negatif.',
            'unit_cost.min' => 'Harga beli tidak boleh negatif.',
        ];
    }

    /**
     * @return array{sku: string, name: string, category: string, unit: string, min_quantity: int, unit_cost: int, supplier: string|null, notes: string|null}
     */
    public function item(): array
    {
        /** @var array{sku: string, name: string, category: string, unit: string, min_quantity: int, unit_cost: int, supplier: string|null, notes: string|null} $data */
        $data = $this->validated();

        return $data;
    }

    /**
     * The SKU is compared against the stored form, which is the canonical one,
     * so it is brought into that form before Rule::unique looks at it.
     */
    protected function prepareForValidation(): void
    {
        $supplier = Str::squish((string) $this->input('supplier', ''));
        $notes = trim((string) $this->input('notes', ''));

        $this->merge([
            'sku' => Str::upper(Str::squish((string) $this->input('sku', ''))),
            'name' => Str::squish((string) $this->input('name', '')),
            'category' => Str::squish((string) $this->input('category', '')),
            'unit' => Str::squish((string) $this->input('unit', '')),
            'supplier' => $supplier === '' ? null : $supplier,
            'notes' => $notes === '' ? null : $notes,
        ]);
    }
}
