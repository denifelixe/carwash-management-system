<?php

namespace App\Http\Requests\Admin;

use App\Models\Member;
use App\Models\MemberVehicle;
use App\Models\Order;
use App\Models\Service;
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
        $existingServiceIds = $booking instanceof Order
            ? $booking->services()->pluck((new Service)->qualifyColumn('id'))->all()
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
            'service_ids' => ['required', 'array', 'min:1'],
            'service_ids.*' => [
                'integer',
                'distinct',
                Rule::exists(Service::class, 'id')->where(
                    fn ($query) => $query->where(
                        fn ($availableQuery) => $availableQuery
                            ->where('is_active', true)
                            ->when($existingServiceIds !== [], fn ($serviceQuery) => $serviceQuery->orWhereIn('id', $existingServiceIds)),
                    ),
                ),
            ],
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

            if (! $booking instanceof Order || $validator->errors()->isNotEmpty()) {
                return;
            }

            $subtotal = (int) Service::query()
                ->whereKey($this->input('service_ids', []))
                ->sum('price');
            $total = max(0, $subtotal - (int) $booking->discount);

            if ($total < (int) $booking->paid_amount) {
                $validator->errors()->add(
                    'service_ids',
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
