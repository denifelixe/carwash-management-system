---
paths:
  - '{app/Actions/Admin/RecordOrderPayment.php,app/Support/Admin/OrderQueries.php,app/Support/Admin/OrderPresenter.php,resources/js/pages/admin/Pos.vue}'
---

# Js Pages Admin

## A payment's shift comes from the cashier, never from the clock
RecordOrderPayment stamps order_transactions.shift_name with the cashier's shift name at write time. It is null when the admin has no work_shift_id, and that null means "no shift" — do not re-derive a shift from paid_at.

The POS recap tabs are built from OrderQueries::workShifts() (active shifts, ordered by starts_at) via OrderPresenter::workShift(), plus a fixed 'tanpa-shift' bucket. A payment matches a tab by exact shift name; anything unmatched — null, or a shift since renamed or retired — falls into Tanpa Shift, so the shift tabs always add up to Total. Never key a tab off substring matching ('pagi'/'sore') or an hour cutoff: both silently drop custom shifts and disagreed with the real windows.

Demo fixtures carry the same field: Operations::orders() stamps each transaction via shiftFor() against the Brand::shifts() windows.
