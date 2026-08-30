<?php

namespace App\Http\Requests\Admin;

class UpdateBookingRequest extends StoreBookingRequest
{
    public function authorize(): bool
    {
        return $this->user('admin')?->can('admin.bookings.update') ?? false;
    }
}
