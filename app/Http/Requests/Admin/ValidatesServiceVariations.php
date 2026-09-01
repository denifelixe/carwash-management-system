<?php

namespace App\Http\Requests\Admin;

use App\Models\Service;
use App\Models\ServiceVariation;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

trait ValidatesServiceVariations
{
    /** @return array<string, mixed> */
    protected function serviceVariationRules(): array
    {
        return [
            'variations' => ['nullable', 'array'],
            'variations.*' => ['required', 'array', 'min:1'],
            'variations.*.*' => ['required', 'string', 'max:100'],
            'service_variations' => ['required', 'array', 'min:1'],
            'service_variations.*.id' => ['nullable', 'integer', Rule::exists(ServiceVariation::class, 'id')],
            'service_variations.*.variations' => ['nullable', 'array'],
            'service_variations.*.variations.*' => ['required', 'string', 'max:100'],
            'service_variations.*.price' => ['required', 'integer', 'min:0', 'max:999999999'],
            'service_variations.*.is_active' => ['required', 'boolean'],
        ];
    }

    /** @return list<callable(Validator): void> */
    protected function serviceVariationAfter(?Service $service = null): array
    {
        return [function (Validator $validator) use ($service): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            /** @var array<string, list<string>>|null $configuration */
            $configuration = $this->input('variations');
            /** @var list<array{id?: int|null, variations: array<string, string>|null, price: int, is_active: bool}> $rows */
            $rows = $this->input('service_variations', []);

            if ($configuration === null) {
                if (count($rows) !== 1 || ($rows[0]['variations'] ?? null) !== null) {
                    $validator->errors()->add(
                        'service_variations',
                        'Layanan tanpa jenis variasi harus memiliki tepat satu harga default.',
                    );
                }
            } else {
                $expected = $this->variationCombinations($configuration);
                $actual = [];

                foreach ($configuration as $attribute => $values) {
                    if (trim($attribute) === '' || count($values) !== count(array_unique($values))) {
                        $validator->errors()->add('variations', 'Nama atribut dan setiap nilainya harus unik.');
                        break;
                    }
                }

                foreach ($rows as $index => $row) {
                    $values = $row['variations'] ?? null;

                    if (! is_array($values) || array_keys($values) !== array_keys($configuration)) {
                        $validator->errors()->add(
                            "service_variations.$index.variations",
                            'Setiap jenis variasi wajib dipilih lengkap.',
                        );

                        continue;
                    }

                    foreach ($values as $attribute => $value) {
                        if (! in_array($value, $configuration[$attribute], true)) {
                            $validator->errors()->add(
                                "service_variations.$index.variations",
                                'Nilai variasi tidak tersedia pada layanan ini.',
                            );
                        }
                    }

                    $actual[] = $this->variationSignature($values);
                }

                sort($actual);
                sort($expected);

                if ($actual !== $expected) {
                    $validator->errors()->add(
                        'service_variations',
                        'Seluruh kombinasi variasi harus tersedia tepat satu kali.',
                    );
                }
            }

            if ($this->boolean('is_active') && ! collect($rows)->contains(
                fn (array $row): bool => (bool) $row['is_active'],
            )) {
                $validator->errors()->add(
                    'service_variations',
                    'Layanan aktif harus memiliki minimal satu variasi aktif.',
                );
            }

            if ($service !== null) {
                $invalidId = collect($rows)->pluck('id')->filter()->first(
                    fn (int $id): bool => ! $service->serviceVariations()->whereKey($id)->exists(),
                );

                if ($invalidId !== null) {
                    $validator->errors()->add('service_variations', 'Variasi tidak dimiliki layanan ini.');
                }
            }
        }];
    }

    /** @param array<string, list<string>> $configuration
     * @return list<string>
     */
    private function variationCombinations(array $configuration): array
    {
        $combinations = [[]];

        foreach ($configuration as $attribute => $values) {
            $next = [];

            foreach ($combinations as $combination) {
                foreach ($values as $value) {
                    $next[] = [...$combination, $attribute => $value];
                }
            }

            $combinations = $next;
        }

        return array_map($this->variationSignature(...), $combinations);
    }

    /** @param array<string, string> $variations */
    private function variationSignature(array $variations): string
    {
        return json_encode($variations, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }
}
