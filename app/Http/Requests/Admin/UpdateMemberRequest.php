<?php

namespace App\Http\Requests\Admin;

use App\Models\Member;
use App\Models\MemberVehicle;
use App\Support\Admin\MemberQueries;
use App\Support\VehiclePlate;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateMemberRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user('admin')?->can('admin.members.update') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Member $member */
        $member = $this->route('member');
        $memberId = (int) $member->getKey();

        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:30', Rule::unique(Member::class, 'phone')->ignore($memberId)],
            'email' => ['nullable', 'email', 'max:255', Rule::unique(Member::class, 'email')->ignore($memberId)],
            'vehicles' => ['required', 'array', 'min:1', 'max:10'],
            'vehicles.*.id' => ['nullable', 'integer', Rule::exists(MemberVehicle::class, 'id')->where('member_id', $memberId)],
            'vehicles.*.name' => ['required', 'string', 'max:255'],
            'vehicles.*.plate' => [
                'required',
                'string',
                'max:20',
                'distinct',
                Rule::unique(MemberVehicle::class, 'plate')
                    ->where(fn ($query) => $query->where('member_id', '!=', $memberId)),
            ],
            'vehicles.*.type' => ['required', Rule::in(MemberQueries::VEHICLE_TYPES)],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.required' => 'Nama member wajib diisi.',
            'phone.required' => 'Nomor HP wajib diisi.',
            'phone.unique' => 'Nomor HP ini sudah dipakai member lain.',
            'email.email' => 'Format email tidak valid.',
            'email.unique' => 'Email ini sudah dipakai member lain.',
            'vehicles.required' => 'Minimal satu kendaraan wajib diisi.',
            'vehicles.min' => 'Minimal satu kendaraan wajib diisi.',
            'vehicles.*.id.exists' => 'Kendaraan tidak terdaftar pada member ini.',
            'vehicles.*.name.required' => 'Nama kendaraan wajib diisi.',
            'vehicles.*.plate.required' => 'Plat nomor wajib diisi.',
            'vehicles.*.plate.distinct' => 'Plat nomor tidak boleh sama dalam satu formulir.',
            'vehicles.*.plate.unique' => 'Plat nomor ini sudah terdaftar sebagai kendaraan member.',
            'vehicles.*.type.in' => 'Jenis kendaraan harus Mobil atau Motor.',
        ];
    }

    /**
     * @return array{name: string, phone: string, email: string|null, vehicles: list<array{id?: int|null, name: string, plate: string, type: string}>}
     */
    public function member(): array
    {
        /** @var array{name: string, phone: string, email: string|null, vehicles: list<array{id?: int|null, name: string, plate: string, type: string}>} $data */
        $data = $this->validated();

        return $data;
    }

    protected function prepareForValidation(): void
    {
        $vehicles = collect($this->input('vehicles', []))
            ->map(fn (mixed $vehicle): mixed => is_array($vehicle) ? [
                ...$vehicle,
                'name' => Str::squish((string) ($vehicle['name'] ?? '')),
                'plate' => VehiclePlate::normalize($vehicle['plate'] ?? null),
            ] : $vehicle)
            ->all();
        $email = trim((string) $this->input('email', ''));

        $this->merge([
            'name' => Str::squish((string) $this->input('name', '')),
            'phone' => trim((string) $this->input('phone', '')),
            'email' => $email === '' ? null : $email,
            'vehicles' => $vehicles,
        ]);
    }
}
