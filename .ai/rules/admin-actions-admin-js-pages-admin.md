---
paths:
  - '{app/Support/Admin/MemberStamps.php,app/Support/Admin/RewardRedemptionRules.php,app/Actions/Admin/RecordOrderPayment.php,app/Http/Requests/Admin/StoreOrderPaymentRequest.php,app/Actions/Admin/DeleteOrder.php,resources/js/pages/admin/Pos.vue}'
---

# Admin Actions Admin Js Pages Admin

## Stamps are a wallet: earned on completed or fully paid orders minus redemptions
A member's balance is never stored. MemberStamps is the only place it is calculated: SUM(orders.stamps_earned) for non-cancelled orders with status = 'selesai' or a positive total fully covered by paid_amount, minus SUM(reward_redemptions.stamps) (soft-deleted rows excluded). Pending and partially paid orders must not show earned stamps in member history. Do not bring back the old `lifetime % stampTarget` card; stampTarget is now only the visual target on the portal card.

The POS posts `reward_id`, and `discount` holds only the cashier's own discount. RewardRedemptionRules::discountFor compares every selected service variation found in the order, using the order's snapshot unit price times min(order quantity, reward quantity) times that variation's percentage, rounded to rupiah. Only the largest candidate applies; ties choose the lowest variation ID. The caller caps it at the amount due. A reward with no variations is merchandise and takes nothing off. Keep the POS preview and demo calculation aligned with the server.

The eligibility rules are checked twice. StoreOrderPaymentRequest::after() checks them unlocked, to return a field error. RecordOrderPayment checks them again with the reward and member rows locked, then decrements stock.

A reward can be redeemed only when the order has no active payment transactions, including merchandise rewards. To redeem after a deposit, delete all of that order's payment transactions first; soft-deleted transactions do not count. Check this in `RewardRedemptionRules::refusal()` so request validation and the locked payment action agree. The POS must hide reward choices on later payments and explain why; an existing redemption remains visible on the order.

Only one active redemption may exist per order. The database enforces this with a nullable `active_slot`: active rows have `1`, voided rows have `NULL`, so a later redemption can retain the old row for audit. Deleting an order voids its redemption and restores stock. Deleting the last active payment transaction voids its redemption, restores stock and stamps, clears `reward_name` and the payment discount, and restores `total` to the service subtotal. Deleting only one of several payment transactions keeps the reward and discount until the last transaction is removed.
