<?php

namespace App\Models;

use Database\Factories\ServiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $service_group_id
 * @property string $name
 * @property string $category
 * @property int $price
 * @property int $stamps
 * @property string $icon
 * @property string|null $description
 * @property bool $is_popular
 * @property bool $is_active
 * @property int $sort_order
 * @property-read int|null $orders_count
 * @property-read ServiceGroup|null $serviceGroup
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['service_group_id', 'name', 'category', 'price', 'stamps', 'icon', 'description', 'is_popular', 'is_active', 'sort_order'])]
class Service extends Model
{
    /** @use HasFactory<ServiceFactory> */
    use HasFactory;

    /** @return BelongsTo<ServiceGroup, $this> */
    public function serviceGroup(): BelongsTo
    {
        return $this->belongsTo(ServiceGroup::class);
    }

    /** @return BelongsToMany<Order, $this> */
    public function orders(): BelongsToMany
    {
        return $this->belongsToMany(Order::class, 'order_services')
            ->withPivot(['service_name', 'unit_price', 'stamps']);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_popular' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
