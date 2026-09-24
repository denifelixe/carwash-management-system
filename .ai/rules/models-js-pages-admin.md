---
paths:
  - '{app/Actions/Admin/UpdateOrder.php,app/Http/Requests/Admin/UpdateOrderRequest.php,app/Models/Order.php,resources/js/pages/admin/Orders.vue}'
---

# Models Js Pages Admin

## A completed, fully paid, or rewarded order keeps its customer
Order::isCustomerLocked() is true when the order has earned stamps (status selesai or a positive total fully paid, excluding cancelled orders) or it has a reward_redemption. At that point its stamps are already in a member's wallet, or have already been spent.

When the order is locked:
- UpdateOrderRequest drops the customer rules.
- UpdateOrder keeps the stored member, vehicle, name, phone and plate, whatever the form posts. The handler is still saved.
- Orders.vue replaces the customer picker with a read-only card, and the detail notice says why.

When services are locked (the order has payments) but the customer is still editable, UpdateOrder recalculates stamps_earned from the order_services pivot. Moving the order to a walk-in drops its stamps.
