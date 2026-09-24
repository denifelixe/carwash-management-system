<?php

namespace App\Http\Requests\Admin;

class UpdateRewardRequest extends StoreRewardRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user('admin')?->can('admin.rewards.update') ?? false;
    }
}
