---
paths:
  - '{app/Actions/Admin/RecordOrderPayment.php,app/Http/Controllers/Admin/FinanceController.php,app/Support/Admin/TransactionShiftResolver.php,resources/js/pages/admin/{Pos,Finance,Users}.vue}'
---

# Admin Js Pages Admin

## Transaction shifts honor each admin's assignment mode
Admins use shift_mode=fixed for their assigned shift (including null) or shift_mode=schedule to resolve active, fully-timed shift windows at transaction time. Schedule matches use start-inclusive/end-exclusive windows with overnight support: zero matches stamps null, one stamps automatically, and overlaps require transaction_shift_id on every new POS or manual Finance transaction. Reports must always read the stored shift_name and never re-derive historical rows from their timestamp.
