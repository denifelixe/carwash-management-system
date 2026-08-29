<?php

namespace App\Http\Requests\Admin;

use App\Models\Member;
use App\Models\MemberVehicle;
use App\Models\Order;
use App\Support\VehiclePlate;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreOrderMemberRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user('admin')?->can('admin.pos.create') ?? false;
    }

    /**
     * The cashier is retyping what the floor captured, so the stray spacing that
     * comes with that is taken off before anything is compared or stored.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => Str::squish((string) $this->input('name')),
            'phone' => trim((string) $this->input('phone')),
            'vehicle_name' => Str::squish((string) $this->input('vehicle_name')),
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
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:30', Rule::unique(Member::class, 'phone')],
            'vehicle_name' => ['required', 'string', 'max:255'],
            'vehicle_plate' => ['required', 'string', 'max:20', Rule::unique(MemberVehicle::class, 'plate')],
        ];
    }

    /**
     * Only an order still standing in the cashier's own name may be handed to a
     * new member; one that already belongs to somebody must not be moved.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $order = $this->order();

            if ($order->member_id !== null) {
                $validator->errors()->add('name', 'Order ini sudah atas nama member.');
            }

            if ($order->status === 'batal') {
                $validator->errors()->add('name', 'Order yang dibatalkan tidak bisa dijadikan member.');
            }
        }];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Nama pelanggan wajib diisi.',
            'phone.required' => 'Nomor telepon wajib diisi.',
            'phone.unique' => 'Nomor telepon ini sudah dipakai member lain.',
            'vehicle_name.required' => 'Tipe mobil wajib diisi.',
            'vehicle_plate.required' => 'Plat nomor wajib diisi.',
            'vehicle_plate.unique' => 'Plat nomor ini sudah terdaftar sebagai kendaraan member.',
        ];
    }

    public function order(): Order
    {
        /** @var Order $order */
        $order = $this->route('order');

        return $order;
    }

    /**
     * @return array{name: string, phone: string, vehicle_name: string, vehicle_plate: string}
     */
    public function member(): array
    {
        return [
            'name' => (string) $this->validated('name'),
            'phone' => (string) $this->validated('phone'),
            'vehicle_name' => (string) $this->validated('vehicle_name'),
            'vehicle_plate' => (string) $this->validated('vehicle_plate'),
        ];
    }
}
