<?php

namespace App\Http\Requests\Admin;

use App\Models\Member;
use App\Models\MemberVehicle;
use App\Models\Service;
use App\Support\VehiclePlate;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOrderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user('admin')?->can('admin.orders.create') ?? false;
    }

    /**
     * The plate is compared against the ones members have registered, so it is
     * brought into the stored form before any rule looks at it.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'vehicle_plate' => VehiclePlate::normalize($this->input('vehicle_plate')),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
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
                /*
                 * A car a member has registered must be billed to that member,
                 * or the visit and its stamps leave no trace on their account.
                 */
                Rule::when(
                    $this->input('customer_mode') === 'walk-in',
                    [Rule::unique(MemberVehicle::class, 'plate')],
                ),
            ],
            'service_ids' => ['required', 'array', 'min:1'],
            'service_ids.*' => ['integer', 'distinct', Rule::exists(Service::class, 'id')->where('is_active', true)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'vehicle_plate.unique' => 'Plat nomor ini sudah terdaftar sebagai kendaraan member. Pilih tab Member untuk membuat order.',
        ];
    }
}
