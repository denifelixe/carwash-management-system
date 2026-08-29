---
paths:
  - '{app/Http/Controllers/Admin/FinanceController.php,app/Support/Admin/Finance*.php,resources/js/pages/admin/Finance.vue}'
---

# Pages Admin

## Money in is derived from POS, never copied
The live finance ledger builds money-in rows from order_transactions via FinancePresenter::posMoneyIn. A cashier payment is never written into cash_entries, so the ledger cannot drift from the till. Those rows are read-only on the page (source: 'pos') and their two categories are stripped from what a hand-written entry may use — see FinanceCategories::recordable.

cash_entries holds only hand-written movements. Outgoing money must carry an attachment (BR-10), enforced in StoreCashEntryRequest; the file lives on the private 'local' disk and is served by admin.finance.attachment, never linked directly.
