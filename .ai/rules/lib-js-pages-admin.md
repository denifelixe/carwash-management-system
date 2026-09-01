---
paths:
  - '{resources/js/lib/recapSheet*.ts,resources/js/pages/admin/Finance.vue}'
---

# Lib Js Pages Admin

## A merged recap figure is written twice, and each paper shows one reading
Non-cash outgoings are booked against the section, not the channel that took the money, so the screen spans one figure down the rows with a rowspan. The printed recap does the same, but only where it can.

RecapTable carries both readings of those figures, from one source in financeRecapSheet(): `merge` (the spanning cells) and `total` (the footed row). Rows supply values only for the columns the table fills row by row — no em-dash placeholders.

HTML: the first row emits `.cell-span` cells with a rowspan, and the tfoot row is classed `merged` instead of `total` when a merge exists. a4Styles hides `.row.merged`; strukStyles hides the spanning cell with `.row > .cell-span`, and it has to be that specific — `.row > td { display: flex }` outranks a bare `.cell-span`, which once left two unlabelled figures sitting under the first channel. Same pattern as `.cell-label`, written for the roll and hidden on the sheet.

PDF: tabularRow skips merged columns and mergedCells() draws them once per page-block, vertically centred with a left rule — a table that breaks closes its span and opens a new one overleaf (verified: 60 rows over 3 A4 pages draws it 3 times). The footed total is suppressed on A4 when a merge exists, or the sheet would state the same figure twice. On the roll a footed row is written like every other block — no rule above it and no extra weight, in both the HTML and the PDF — so the closing figures read alongside the channels rather than louder than them. The emphasis A4 gives its foot comes from a4Styles' own tfoot rules, not from a .row.total override.

A section heading must not repeat its own first column ("Kanal Keuangan" over a "Kanal Keuangan" column); name it for what it holds — "Tunai", "Non-Tunai".


## The recap prints the balance the day opened from, then the one it closed on
FinanceQueries::dailyBalance() (and its demo twin Support/Demo/Finance) returns {cash, nonCash, previous: {date, cash, nonCash}} — both read through the same balanceAsOf() lookup, which takes the latest snapshot on or before that day because a day with no movement leaves no snapshot of its own. Keep the two in step: the live and demo controllers must send the same prop set.

The balance is an accumulation, so a sheet carrying only the closing figure would not say what the day moved. The Saldo Kas table prints the opening row first and the closing row second, and it is the last table on the recap because it is a closing position rather than a breakdown of the day.
