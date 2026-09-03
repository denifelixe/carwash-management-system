<?php

namespace App\Actions\Admin;

use App\Models\Member;
use App\Models\MemberVehicle;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

/**
 * Registers the walk-in behind an order as a member, at the till (BR-06).
 *
 * A customer usually agrees to join while the cashier is already holding their
 * bill, so the order is not copied to the new member — it is moved onto them.
 * Every payment booked against it, before or after, therefore belongs to that
 * member, and the stamps the visit was always worth are finally counted.
 */
class RegisterOrderMember
{
    public function __construct(private MarkLeadConverted $markLeadConverted) {}

    /**
     * @param  array{name: string, phone: string, vehicle_name: string, vehicle_plate: string}  $details
     */
    public function handle(Order $order, array $details): Member
    {
        return DB::transaction(function () use ($order, $details): Member {
            $order = Order::query()->whereKey($order->getKey())->lockForUpdate()->firstOrFail();

            /*
             * The form was validated against an unlocked read, so the order is
             * checked again here: two tills registering the same walk-in at once
             * would otherwise leave one member holding nothing.
             */
            abort_if($order->member_id !== null, 422, 'Order ini sudah atas nama member.');
            abort_if($order->status === 'batal', 422, 'Order yang dibatalkan tidak bisa dijadikan member.');

            /*
             * No email and no password: those are portal credentials, and the
             * counter has neither. The member exists, they simply cannot sign in
             * until they ask to.
             */
            $member = Member::query()->create([
                'name' => $details['name'],
                'phone' => $details['phone'],
            ]);

            /** @var MemberVehicle $vehicle */
            $vehicle = $member->vehicles()->create([
                'name' => $details['vehicle_name'],
                /* The category, not the make — the make is the vehicle's name. */
                'type' => 'Mobil',
                'plate' => $details['vehicle_plate'],
                'is_primary' => true,
            ]);

            $order->update([
                'member_id' => $member->id,
                'member_vehicle_id' => $vehicle->id,
                'customer_name' => $member->name,
                'customer_phone' => (string) $member->phone,
                'vehicle_name' => $vehicle->name,
                'vehicle_plate' => $vehicle->plate,
                /*
                 * A walk-in order is written with no stamps, because there was
                 * nobody to hold them. There is now, so the visit is worth what
                 * its services have always been worth.
                 */
                'stamps_earned' => (int) $order->serviceVariations()->get()->sum(
                    fn ($variation): int => (int) $variation->pivot->stamps * (int) $variation->pivot->quantity,
                ),
            ]);

            $this->markLeadConverted->handle($member, [$vehicle->plate]);

            return $member;
        });
    }
}
