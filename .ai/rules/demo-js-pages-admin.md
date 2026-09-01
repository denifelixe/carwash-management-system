---
paths:
  - '{app/Support/Admin/Finance*.php,app/Support/Demo/Finance.php,resources/js/pages/admin/Finance.vue}'
---

# Demo Js Pages Admin

## Ledger rows are filed by the writer's shift, never by the hour
FinancePresenter passes order_transactions.shift_name / cash_entries.shift_name straight through — no clock fallback. FinanceQueries::shiftNameFor() was deleted; do not reintroduce it or any time-window inference.

Finance.vue's tab strip is 'all' + one tab per props.shifts + a fixed 'tanpa-shift' bucket, matched on exact shift name. Null, or a name whose shift was renamed or retired, falls into Tanpa Shift so the tabs add up. Same structure as the POS recap in Pos.vue.

Demo mirrors this: Finance::posMoneyIn() carries the shift already stamped on the transaction, manual and expense rows take it from RoleAccess::staff() via shiftOf(), and shiftSummary() aggregates by shift name with vehiclesServed counted as distinct orderIds in that shift's POS income — matching FinanceQueries::shiftSummary().

## The balance card reads daily_balance snapshots, never a re-summed ledger
Finance.vue's Saldo card shows FinanceQueries::dailyBalance (latest snapshot on or before the selected date) and opens a dialog over FinanceQueries::dailyBalanceHistory — the same snapshots, newest first, capped at FinanceQueries::BALANCE_HISTORY_DAYS. Never re-sum the ledger for either: daily_balance is maintained by UpdateDailyBalance at write time.

Only days that moved money own a snapshot, so a quiet day is absent from the history rather than repeated as a flat line. The dialog says as much; do not fill the gaps client-side.

Demo mirrors the shape from fixtures in Finance::dailyBalanceHistory, and Finance::dailyBalance is its newest row — keep the two in step so both modes hand the page identical props.
