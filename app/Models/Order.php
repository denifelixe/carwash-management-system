<?php

namespace App\Models;

use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
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
#[Fillable(['number', 'invoice_number', 'member_id', 'member_vehicle_id', 'created_by_admin_id', 'crew_admin_id', 'customer_name', 'customer_phone', 'vehicle_name', 'vehicle_plate', 'service_date', 'arrived_at', 'booking_date', 'source', 'status', 'subtotal', 'discount', 'total', 'paid_amount', 'stamps_earned', 'reward_name', 'payment_method', 'bay', 'notes'])]
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
    public function crew(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'crew_admin_id');
    }

    /** @return BelongsToMany<Service, $this> */
    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'order_services')
            ->withPivot(['service_name', 'unit_price', 'stamps']);
    }

    /** @return HasMany<OrderTransaction, $this> */
    public function transactions(): HasMany
    {
        return $this->hasMany(OrderTransaction::class)->orderBy('paid_at');
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
