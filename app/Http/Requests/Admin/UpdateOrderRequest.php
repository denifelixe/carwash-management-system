<?php

namespace App\Http\Requests\Admin;

use App\Models\Order;
use App\Models\ServiceVariation;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

class UpdateOrderRequest extends StoreOrderRequest
{
    /** @var list<string> */
    private const CUSTOMER_FIELDS = ['customer_mode', 'member_id', 'member_vehicle_id', 'customer_name', 'customer_phone', 'vehicle_name', 'is_special_plate', 'vehicle_plate'];

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user('admin')?->can('admin.orders.update') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Order $order */
        $order = $this->route('order');
        $rules = parent::rules();

        /* UpdateOrder keeps the stored customer on a locked order, so the one posted is not checked. */
        if ($order->isCustomerLocked()) {
            $rules = Arr::except($rules, self::CUSTOMER_FIELDS);
        }

        return [
            ...$rules,
            'items.*.service_variation_id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists(ServiceVariation::class, 'id'),
            ],
        ];
    }
}
