<?php

namespace App\Http\Requests\Admin;

use App\Models\Lead;
use App\Models\MemberVehicle;
use App\Support\VehiclePlate;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreLeadRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user('admin')?->can('admin.leads.create') ?? false;
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
            'phone' => ['nullable', 'string', 'max:30'],
            'vehicle_name' => ['nullable', 'string', 'max:255'],
            'vehicle_plate' => [
                'required',
                'string',
                'max:20',
                Rule::unique(Lead::class, 'vehicle_plate'),
                /*
                 * A car already on a member's account is not a lead: it belongs
                 * on the Member tab, the same way StoreOrderRequest decides.
                 */
                Rule::unique(MemberVehicle::class, 'plate'),
            ],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.required' => 'Nama calon pelanggan wajib diisi.',
            'vehicle_plate.required' => 'Plat nomor wajib diisi.',
            'vehicle_plate.unique' => 'Plat nomor ini sudah tercatat sebagai lead atau kendaraan member.',
        ];
    }

    /**
     * @return array{name: string, phone: string|null, vehicle_name: string|null, vehicle_plate: string, notes: string|null}
     */
    public function lead(): array
    {
        /** @var array{name: string, phone: string|null, vehicle_name: string|null, vehicle_plate: string, notes: string|null} $data */
        $data = $this->validated();

        return $data;
    }

    /**
     * The plate is compared against the stored form, which is the canonical one,
     * so it is brought into that form before any rule looks at it.
     */
    protected function prepareForValidation(): void
    {
        $phone = trim((string) $this->input('phone', ''));
        $vehicleName = Str::squish((string) $this->input('vehicle_name', ''));
        $notes = trim((string) $this->input('notes', ''));

        $this->merge([
            'name' => Str::squish((string) $this->input('name', '')),
            'phone' => $phone === '' ? null : $phone,
            'vehicle_name' => $vehicleName === '' ? null : $vehicleName,
            'vehicle_plate' => VehiclePlate::normalize($this->input('vehicle_plate')),
            'notes' => $notes === '' ? null : $notes,
        ]);
    }
}
