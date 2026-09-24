<?php

namespace App\Http\Requests\Admin;

use App\Models\ServiceVariation;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreRewardRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user('admin')?->can('admin.rewards.create') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
            'icon' => ['required', 'string', 'max:16'],
            'category' => ['required', 'string', 'max:100'],
            'required_stamps' => ['required', 'integer', 'min:1', 'max:1000'],
            'stock' => ['required', 'integer', 'min:0', 'max:1000000'],
            'is_active' => ['required', 'boolean'],
            'variation_discounts' => ['present', 'array'],
            'variation_discounts.*.service_variation_id' => ['required', 'integer', 'distinct', Rule::exists(ServiceVariation::class, 'id')],
            'variation_discounts.*.quantity' => ['required', 'integer', 'min:1', 'max:1000'],
            'variation_discounts.*.discount_percent' => ['required', 'integer', 'min:1', 'max:100'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.required' => 'Nama reward wajib diisi.',
            'icon.required' => 'Ikon reward wajib diisi.',
            'category.required' => 'Kategori wajib diisi.',
            'required_stamps.required' => 'Syarat stempel wajib diisi.',
            'required_stamps.min' => 'Syarat stempel minimal 1.',
            'stock.min' => 'Stok tidak boleh negatif.',
            'variation_discounts.*.service_variation_id.exists' => 'Variasi layanan yang dipilih tidak ditemukan.',
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $existingIds = $this->route('reward')?->serviceVariations()->pluck('service_variations.id')->all() ?? [];
            $rows = $this->input('variation_discounts', []);
            $variations = ServiceVariation::query()->with('service')
                ->whereKey(array_column($rows, 'service_variation_id'))
                ->get()->keyBy('id');

            foreach ($rows as $index => $row) {
                $variation = $variations->get($row['service_variation_id']);

                if ($variation !== null && (! $variation->is_active || ! $variation->service->is_active)
                    && ! in_array($variation->id, $existingIds, true)) {
                    $validator->errors()->add("variation_discounts.$index.service_variation_id", 'Variasi nonaktif tidak dapat ditambahkan.');
                }
            }
        }];
    }

    /**
     * @return array{name: string, description: string|null, icon: string, category: string, required_stamps: int, stock: int, is_active: bool, variation_discounts: list<array{service_variation_id: int, quantity: int, discount_percent: int}>}
     */
    public function reward(): array
    {
        /** @var array{name: string, description: string|null, icon: string, category: string, required_stamps: int, stock: int, is_active: bool, variation_discounts: list<array{service_variation_id: int, quantity: int, discount_percent: int}>} $data */
        $data = [
            ...$this->validated(),
            'is_active' => $this->boolean('is_active'),
            'variation_discounts' => array_values($this->validated('variation_discounts', [])),
        ];

        return $data;
    }

    protected function prepareForValidation(): void
    {
        $description = trim((string) $this->input('description', ''));

        $this->merge([
            'name' => Str::squish((string) $this->input('name', '')),
            'icon' => trim((string) $this->input('icon', '')),
            'category' => Str::squish((string) $this->input('category', '')),
            'description' => $description === '' ? null : $description,
            'variation_discounts' => $this->input('variation_discounts', []),
        ]);
    }
}
