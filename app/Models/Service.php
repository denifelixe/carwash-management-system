<?php

namespace App\Models;

use Database\Factories\ServiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $category
 * @property array<string, list<string>>|null $variations
 * @property int $stamps
 * @property string $icon
 * @property string|null $description
 * @property bool $is_popular
 * @property bool $is_active
 * @property int $sort_order
 * @property-read int|null $orders_count
 * @property-read Collection<int, ServiceVariation> $serviceVariations
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'category', 'variations', 'stamps', 'icon', 'description', 'is_popular', 'is_active', 'sort_order'])]
class Service extends Model
{
    /** @use HasFactory<ServiceFactory> */
    use HasFactory;

    private int $pendingVariationPrice = 0;

    public function setPriceAttribute(int $price): void
    {
        $this->pendingVariationPrice = $price;
    }

    public function getPriceAttribute(): int
    {
        $variation = $this->relationLoaded('serviceVariations')
            ? $this->serviceVariations->first()
            : $this->serviceVariations()->orderBy('id')->first();

        return (int) ($variation?->price ?? $this->pendingVariationPrice);
    }

    public function pendingVariationPrice(): int
    {
        return $this->pendingVariationPrice;
    }

    /** @return HasMany<ServiceVariation, $this> */
    public function serviceVariations(): HasMany
    {
        return $this->hasMany(ServiceVariation::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_popular' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'variations' => 'array',
        ];
    }
}
