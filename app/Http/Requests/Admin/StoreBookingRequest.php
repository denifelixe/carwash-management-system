<?php

namespace App\Http\Requests\Admin;

use App\Models\Member;
use App\Models\MemberVehicle;
use App\Models\Order;
use App\Models\ServiceVariation;
use App\Support\VehiclePlate;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('admin')?->can('admin.bookings.create') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'vehicle_plate' => VehiclePlate::normalize($this->input('vehicle_plate')),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $booking = $this->route('order');
        $existingVariationIds = $booking instanceof Order
            ? $booking->serviceVariations()->pluck((new ServiceVariation)->qualifyColumn('id'))->all()
            : [];

        return [
            'customer_mode' => ['required', Rule::in(['existing', 'walk-in'])],
            'member_id' => ['nullable', 'required_if:customer_mode,existing', Rule::exists(Member::class, 'id')->where('is_active', true)],
            'member_vehicle_id' => [
                'nullable',
                'required_if:customer_mode,existing',
                Rule::exists(MemberVehicle::class, 'id')->where(
                    fn ($query) => $query->where('member_id', $this->integer('member_id')),
                ),
            ],
            'customer_name' => ['nullable', 'required_if:customer_mode,walk-in', 'string', 'max:255'],
            'customer_phone' => ['nullable', 'required_if:customer_mode,walk-in', 'string', 'max:30'],
            'vehicle_name' => ['nullable', 'required_if:customer_mode,walk-in', 'string', 'max:255'],
            'vehicle_plate' => [
                'nullable',
                'required_if:customer_mode,walk-in',
                'string',
                'max:20',
                Rule::when(
                    $this->input('customer_mode') === 'walk-in',
                    [Rule::unique(MemberVehicle::class, 'plate')],
                ),
            ],
            'items' => ['required', 'array', 'min:1'],
            'items.*.service_variation_id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists(ServiceVariation::class, 'id')->where(
                    fn ($query) => $query->where(
                        fn ($availableQuery) => $availableQuery
                            ->where('is_active', true)
                            ->when(
                                $existingVariationIds !== [],
                                fn ($variationQuery) => $variationQuery->orWhereIn('id', $existingVariationIds),
                            ),
                    ),
                ),
            ],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:999'],
            'service_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $booking = $this->route('order');

            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $existingVariationIds = $booking instanceof Order
                ? $booking->serviceVariations()->pluck((new ServiceVariation)->qualifyColumn('id'))->all()
                : [];

            $quantities = collect($this->input('items', []))->mapWithKeys(
                fn (array $item): array => [(int) $item['service_variation_id'] => (int) $item['quantity']],
            );
            $variations = ServiceVariation::query()->with('service')->whereKey($quantities->keys())->get();

            if ($variations->contains(
                fn (ServiceVariation $variation): bool => ! $variation->service->is_active
                    && ! in_array($variation->id, $existingVariationIds, true),
            )) {
                $validator->errors()->add('items', 'Pilihan layanan tidak lagi tersedia.');

                return;
            }

            if (! $booking instanceof Order) {
                return;
            }

            $subtotal = (int) $variations->sum(
                fn (ServiceVariation $variation): int => $variation->price * $quantities[$variation->id],
            );
            $total = max(0, $subtotal - (int) $booking->discount);

            if ($total < (int) $booking->paid_amount) {
                $validator->errors()->add(
                    'items',
                    'Total layanan tidak boleh lebih kecil dari pembayaran yang sudah diterima.',
                );
            }
        }];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'vehicle_plate.unique' => 'Plat nomor ini sudah terdaftar sebagai kendaraan member. Pilih tab Member untuk membuat booking.',
            'service_date.after_or_equal' => 'Tanggal kedatangan tidak boleh sebelum hari ini.',
        ];
    }
}
