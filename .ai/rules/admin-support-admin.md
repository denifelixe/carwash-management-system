---
paths:
  - '{app/Http/Controllers/Admin/DashboardController.php,app/Support/Admin/DashboardStats.php}'
---

# Admin Support Admin

## The dashboard restates other modules, it never counts
Every figure on the live dashboard comes from the module that owns it — FinanceQueries::ledgerForDate/cashSummary/shiftSummary for money and shifts, OrderQueries::summaryForDate for vehicles, Member for the member base. Never add a query here that counts the same thing a different way; that is how the dashboard drifted to all-zeros placeholders before.

DashboardStats::forDate takes the day's moneyIn so the ledger is read once and both the cards and cashSummary come off it. 'Stempel Ditukar' stays 0 until the reward module goes live.
