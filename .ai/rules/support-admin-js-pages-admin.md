---
paths:
  - '{app/Support/Admin/ReportQueries.php,app/Support/Admin/DashboardStats.php,resources/js/pages/admin/Reports.vue}'
---

# Support Admin Js Pages Admin

## Figures no module owns yet are served as zero with the page saying why
The Rewards module has shipped: redemptions live in reward_redemptions, and the dashboard's 'Stempel Ditukar' reads RewardQueries::redeemedOnDate(). The live report still has no loyalty card; if one is added, read it from RewardQueries/MemberStamps rather than synthesising figures.

Prop parity is why the zeroed props exist at all: .ai/rules/admin.md requires both the live and demo controller to hand admin/Reports the same prop set, so the live side cannot simply drop them.

Inventory was the other half of this rule and no longer applies: the stock module shipped, ReportQueries::EMPTY_INVENTORY is gone in favour of ReportQueries::inventorySummary() over the real tables, and the card's "Modul inventory belum aktif" EmptyState came out with it.

## The inventory card takes no range, and must stay out of the range reload
inventorySummary() answers for right now — on hand is a current figure and movementsThisWeek is a rolling seven days from stock_movements.recorded_at — so unlike every other card it ignores the report's from/to. Reports.vue::applyRange() therefore omits it from its `only:` list on purpose. Adding it there would refetch the same numbers on every date change; giving it a range would make it disagree with the Inventory module's own totals.
