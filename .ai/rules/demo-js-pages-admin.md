---
paths:
  - '{app/Support/Admin/Finance*.php,app/Support/Demo/Finance.php,resources/js/pages/admin/Finance.vue}'
---

# Demo Js Pages Admin

## Ledger rows are filed by the writer's shift, never by the hour
FinancePresenter passes order_transactions.shift_name / cash_entries.shift_name straight through — no clock fallback. FinanceQueries::shiftNameFor() was deleted; do not reintroduce it or any time-window inference.

Finance.vue's tab strip is 'all' + one tab per props.shifts + a fixed 'tanpa-shift' bucket, matched on exact shift name. Null, or a name whose shift was renamed or retired, falls into Tanpa Shift so the tabs add up. Same structure as the POS recap in Pos.vue.

Demo mirrors this: Finance::posMoneyIn() carries the shift already stamped on the transaction, manual and expense rows take it from RoleAccess::staff() via shiftOf(), and shiftSummary() aggregates by shift name with vehiclesServed counted as distinct orderIds in that shift's POS income — matching FinanceQueries::shiftSummary().
