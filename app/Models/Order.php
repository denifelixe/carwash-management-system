<?php

namespace App\Models;

use App\Support\VehiclePlate;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $number
 * @property string|null $invoice_number
 * @property int|null $member_id
 * @property int|null $member_vehicle_id
 * @property int|null $created_by_admin_id
 * @property int|null $crew_admin_id
 * @property int|null $handled_by_admin_id
 * @property string|null $handled_by
 * @property string $customer_name
 * @property string $customer_phone
 * @property string $vehicle_name
 * @property string $vehicle_plate
 * @property Carbon $service_date
 * @property Carbon|null $arrived_at
 * @property Carbon|null $booking_date
 * @property string $source
 * @property string $status
 * @property int $subtotal
 * @property int $discount
 * @property int $total
 * @property int $paid_amount
 * @property int $stamps_earned
 * @property string|null $reward_name
 * @property string|null $payment_method
 * @property string|null $bay
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['number', 'invoice_number', 'member_id', 'member_vehicle_id', 'created_by_admin_id', 'crew_admin_id', 'handled_by_admin_id', 'handled_by', 'customer_name', 'customer_phone', 'vehicle_name', 'vehicle_plate', 'service_date', 'arrived_at', 'booking_date', 'source', 'status', 'subtotal', 'discount', 'total', 'paid_amount', 'stamps_earned', 'reward_name', 'payment_method', 'bay', 'notes'])]
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    /** @return BelongsTo<Member, $this> */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /** @return BelongsTo<MemberVehicle, $this> */
    public function memberVehicle(): BelongsTo
    {
        return $this->belongsTo(MemberVehicle::class);
    }

    /** @return BelongsTo<Admin, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by_admin_id');
    }

    /** @return BelongsTo<Admin, $this> */
    public function handledByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'handled_by_admin_id');
    }

    /** @return BelongsTo<Admin, $this> */
    public function crew(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'crew_admin_id');
    }

    /** @return BelongsToMany<ServiceVariation, $this> */
    public function serviceVariations(): BelongsToMany
    {
        return $this->belongsToMany(ServiceVariation::class, 'order_services')
            ->withPivot(['service_name', 'variations', 'unit_price', 'quantity', 'total_price', 'stamps']);
    }

    /** @return HasMany<OrderTransaction, $this> */
    public function transactions(): HasMany
    {
        return $this->hasMany(OrderTransaction::class)->orderBy('paid_at');
    }

    /**
     * Kept in the same canonical form as a member's vehicle, so the car an
     * order was written for can be matched against the ones already registered.
     *
     * @return Attribute<never, string>
     */
    protected function vehiclePlate(): Attribute
    {
        return Attribute::set(fn (?string $plate): string => VehiclePlate::normalize($plate));
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'service_date' => 'date',
            'arrived_at' => 'datetime',
            'booking_date' => 'date',
        ];
    }
}
