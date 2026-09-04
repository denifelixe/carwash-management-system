<?php

namespace App\Http\Requests\Admin;

use App\Models\ServiceVariation;
use Illuminate\Validation\Rule;

class UpdateOrderRequest extends StoreOrderRequest
{
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
        return [
            ...parent::rules(),
            'items.*.service_variation_id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists(ServiceVariation::class, 'id'),
            ],
        ];
    }
}
