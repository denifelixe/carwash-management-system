---
paths:
  - '{app/Http/Controllers/Admin/FinanceController.php,app/Support/Admin/Finance*.php,resources/js/pages/admin/Finance.vue}'
  - '{app/Http/Controllers/Admin/ReportController.php,app/Support/Admin/ReportQueries.php,resources/js/pages/admin/Reports.vue}'
---

# Pages Admin

## Money in is derived from POS, never copied
The live finance ledger builds money-in rows from order_transactions via FinancePresenter::posMoneyIn. A cashier payment is never written into cash_entries, so the ledger cannot drift from the till. Those rows are read-only on the page (source: 'pos') and their two categories are stripped from what a hand-written entry may use — see FinanceCategories::recordable.

cash_entries holds only hand-written movements. Outgoing money must carry an attachment (BR-10), enforced in StoreCashEntryRequest; the file lives on the private 'local' disk and is served by admin.finance.attachment, never linked directly.

## The report restates other modules over a range, and says which measure it is showing
Revenue is attributed to the payment date (order_transactions.paid_at), never the service day, so a range total reconciles exactly with FinanceQueries::ledgerForDate for every day it covers. AdminReportTest asserts that reconciliation — keep it.

order_transactions.amount is the booked figure and is what SUM()s into revenue. channel_breakdown is the tender and carries cash change, so PaymentChannelBreakdown::financial() belongs nowhere in this module; it only labels channels.

topServices is a different measure from trend: it sums the order_services snapshot (value sold) for orders paid in the range, so a half-paid order contributes its whole service value there and only its payment to the chart. The card caption says "Nilai layanan terjual" for exactly that reason — do not retitle it to imply the two tie out.

Bookings are counted on service_date, since a booking is an operational commitment rather than a payment.

Shift rows are grouped by the stamped shift_name and every name that matches no active AdminShift — plus NULL — folds into the tanpa-shift bucket, same rule Finance follows. The unassigned POS row is read by its own query, not summed from the grouped ones, so one order paid twice under two retired names still counts as one vehicle. There is no `status` on a range row: running-or-finished is a today-only fact.</note>
</invoke>
