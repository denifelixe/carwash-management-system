---
paths:
  - '{app/Support/Admin/ReportQueries.php,app/Support/Admin/DashboardStats.php,resources/js/pages/admin/Reports.vue}'
---

# Support Admin Js Pages Admin

## Figures no module owns yet are served as zero with the page saying why
There are no inventory tables and nothing records a stamp redemption, so the live report serves ReportQueries::EMPTY_INVENTORY and holds customerActivity.stampsRedeemed / rewardsClaimed at 0 — the same precedent the dashboard sets for 'Stempel Ditukar'. Never synthesise these.

The page must carry the explanation, not just the zero: the inventory card renders an EmptyState and the stamp-redemption bar prints a note when stamps were issued but none redeemed, so a flat 0% does not read as a bug. When the Inventory or Rewards module ships, the figures replace the constants and those two notices come out.

Prop parity is why the zeroed props exist at all: .ai/rules/admin.md requires both the live and demo controller to hand admin/Reports the same prop set, so the live side cannot simply drop them.</note>
</invoke>
