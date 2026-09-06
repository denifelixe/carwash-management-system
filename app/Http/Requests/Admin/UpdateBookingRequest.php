<?php

namespace App\Http\Requests\Admin;

use App\Models\Order;
use Illuminate\Contracts\Validation\ValidationRule;

class UpdateBookingRequest extends StoreBookingRequest
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $rules = parent::rules();
        $booking = $this->route('order');

        if ($booking instanceof Order
            && $booking->service_date->isBefore(today())
            && $this->input('service_date') === $booking->service_date->toDateString()) {
            $rules['service_date'] = ['required', 'date_format:Y-m-d'];
        }

        return $rules;
    }

    public function authorize(): bool
    {
        return $this->user('admin')?->can('admin.bookings.update') ?? false;
    }
}
