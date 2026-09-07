---
paths:
  - '{app/Http/Controllers/Admin/OrderController.php,app/Actions/Admin/DeleteOrderTransaction.php,resources/js/pages/admin/Orders.vue}'
---

# Actions Admin Js Pages Admin

## Completed order statuses reopen only through payment deletion
Orders with status selesai cannot have their status changed manually, including by owners. Hide status controls in both row and detail views, guard demo mutations, and reject server status writes after reloading the order under lock. Deleting a payment reopens a completed order to pelunasan only when the remaining paid amount is below its total; preserve this route for correcting payments.
