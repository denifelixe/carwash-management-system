---
paths:
  - '{app/Actions/Admin/RecordOrderPayment.php,app/Http/Controllers/Admin/FinanceController.php,app/Support/Admin/TransactionShiftResolver.php,resources/js/pages/admin/{Pos,Finance,Users}.vue}'
---

# Admin Js Pages Admin

## Transaction shifts honor each admin's assignment mode
Admins use shift_mode=fixed for their assigned shift (including null) or shift_mode=schedule to resolve active, fully-timed shift windows at transaction time. Schedule matches use start-inclusive/end-exclusive windows with overnight support: zero matches stamps null, one stamps automatically, and overlaps require transaction_shift_id on every new POS or manual Finance transaction. Reports must always read the stored shift_name and never re-derive historical rows from their timestamp.

## Transaction shifts honor each admin's assignment mode
Admins use shift_mode=fixed for their assigned shift (including null), or schedule for the active fully-timed windows captured in the login session. Schedule windows are start-inclusive/end-exclusive with overnight support: zero matches stamps null, one stamps automatically, and overlaps must be selected once in the login popup via admin.login-shift.confirm. The selected snapshot stays locked until another login; POS and manual Finance transactions cannot override it with transaction_shift_id. Reports always read stored shift_name and never infer historical shifts from timestamps. This replaces the former per-transaction overlap selection rule; demo retains its existing behavior.
