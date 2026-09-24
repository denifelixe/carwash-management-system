---
paths:
  - '{app/Actions/Admin/RecordCashDeposit.php,app/Actions/Admin/DeleteCashEntry.php,app/Http/Controllers/Admin/FinanceController.php,app/Http/Requests/Admin/UpdateCashEntryRequest.php,resources/js/pages/admin/Finance.vue}'
---

# Requests Admin Js Pages Admin

## Setor Tunai is two cash entries that live and die together
A Setor Tunai (RecordCashDeposit, POST admin.finance.deposits.store) writes a Tunai outflow and a "Setor Tunai" inflow (category FinanceCategories::CASH_DEPOSIT, method OrderQueries::CASH_DEPOSIT_METHOD) sharing cash_entries.transfer_reference, amount, description, time, shift and proofs (each entry stores its own copy of every file). Both count in income/expense totals by user decision. Editing either (FinanceController@update) mirrors amount/description/time/shift/proofs onto CashEntry::transferPartner() and keeps category/method fixed; DeleteCashEntry removes both. The category/method are excluded from FinanceCategories::recordable / recordableMethods and never enter PAYMENT_METHODS, so only the Setor Tunai form can create a pair.
