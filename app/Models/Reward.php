<?php

namespace App\Models;

use Database\Factories\RewardFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Something a member can trade stamps for at the till (BR-04, BR-13).
 *
 * A reward discounts the eligible variation with the largest discount.
 * One with no variations is merchandise and takes nothing off.
 * `stock` is only ever decremented by RecordOrderPayment, under a row lock.
 *
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property string $icon
 * @property string $category
 * @property int $required_stamps
 * @property int $stock
 * @property bool $is_active
 * @property-read int|null $redemptions_count
 * @property-read Collection<int, ServiceVariation> $serviceVariations
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'description', 'icon', 'category', 'required_stamps', 'stock', 'is_active'])]
class Reward extends Model
{
    /** @use HasFactory<RewardFactory> */
    use HasFactory;

    /** @return BelongsToMany<ServiceVariation, $this> */
    public function serviceVariations(): BelongsToMany
    {
        return $this->belongsToMany(ServiceVariation::class, 'reward_service_variation')
            ->withPivot(['quantity', 'discount_percent']);
    }

    /** @return HasMany<RewardRedemption, $this> */
    public function redemptions(): HasMany
    {
        return $this->hasMany(RewardRedemption::class);
    }

    public function isMerchandise(): bool
    {
        return $this->serviceVariations->isEmpty();
    }

    /**
     * @param  Builder<Reward>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'required_stamps' => 'integer',
            'stock' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
