<?php

namespace App\Models;

use Database\Factories\ServiceVariationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $service_id
 * @property array<string, string>|null $variations
 * @property int $price
 * @property bool $is_active
 * @property-read Service $service
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['service_id', 'variations', 'price', 'is_active'])]
class ServiceVariation extends Model
{
    /** @use HasFactory<ServiceVariationFactory> */
    use HasFactory;

    /** @return BelongsTo<Service, $this> */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /** @return BelongsToMany<Order, $this> */
    public function orders(): BelongsToMany
    {
        return $this->belongsToMany(Order::class, 'order_services')
            ->withPivot(['service_name', 'variations', 'unit_price', 'quantity', 'total_price', 'stamps']);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'variations' => 'array',
            'price' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
