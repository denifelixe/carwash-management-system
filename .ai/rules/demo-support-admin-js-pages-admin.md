---
paths:
  - '{app/Support/Admin/ReportQueries.php,app/Support/Demo/Reports.php,app/Support/Admin/DailySalesCsv.php,resources/js/pages/admin/Reports.vue}'
---

# Demo Support Admin Js Pages Admin

## Daily sales splits by method with the financial breakdown, and only there
ReportQueries::dailySales (Laporan Penjualan Harian) is the one exception to "PaymentChannelBreakdown::financial() belongs nowhere in the report module": its per-method columns use the financial allocation, because the tender carries cash change and would make Tunai exceed the day. Day totals are still SUM(order_transactions.amount) by paid_at, so dailySales.total.total equals the trend's revenue and the method columns foot to it. Every day of the range gets a row (quiet days are zeros). Demo\Reports::dailySales mirrors the shape from dayFigures with a fixed method mix. The prop is in Reports.vue's applyRange `only:` list; the CSV is DailySalesCsv (';', BOM) at admin.reports.daily-sales.export.
