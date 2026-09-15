---
paths:
  - '{app/Support/Admin/ReportQueries.php,app/Support/Admin/DashboardStats.php,resources/js/pages/admin/Reports.vue}'
---

# Support Admin Js Pages Admin

## Figures no module owns yet are served as zero with the page saying why
Nothing records a stamp redemption yet, so the live report holds customerActivity.stampsRedeemed / rewardsClaimed at 0 — the same precedent the dashboard sets for 'Stempel Ditukar'. Never synthesise these.

The page must carry the explanation, not just the zero: the stamp-redemption bar prints a note when stamps were issued but none redeemed, so a flat 0% does not read as a bug. When the Rewards module ships, the figures replace the constants and that notice comes out.

Prop parity is why the zeroed props exist at all: .ai/rules/admin.md requires both the live and demo controller to hand admin/Reports the same prop set, so the live side cannot simply drop them.

Inventory was the other half of this rule and no longer applies: the stock module shipped, ReportQueries::EMPTY_INVENTORY is gone in favour of ReportQueries::inventorySummary() over the real tables, and the card's "Modul inventory belum aktif" EmptyState came out with it.

## The inventory card takes no range, and must stay out of the range reload
inventorySummary() answers for right now — on hand is a current figure and movementsThisWeek is a rolling seven days from stock_movements.recorded_at — so unlike every other card it ignores the report's from/to. Reports.vue::applyRange() therefore omits it from its `only:` list on purpose. Adding it there would refetch the same numbers on every date change; giving it a range would make it disagree with the Inventory module's own totals.
