---
paths:
  - 'resources/js/lib/**'
---

# Lib

## Printed documents are standalone HTML in their own window, not a PDF library
The POS slip (posReceipt.ts) and the shift recaps (recapSheet.ts) are written as self-contained HTML into a window opened with window.open, with a [Cetak] toolbar hidden by @media print. Download is the browser's own "Save as PDF" from that prompt — there is no dompdf/jsPDF, and no server route renders a document. Do not add one.

Handlers are wired with addEventListener after document.write, never inline <script>, so the written document carries no executable markup. openPosReceiptWindow/openRecapSheetWindow return null when the browser blocks the window; the page must flag that, not swallow it.

escapeHtml, formatWhatsapp and printedAt live in printDocument.ts and are shared by both — every value on a printed document comes from user input, so nothing reaches it raw, and there must be exactly one escaper. printedAt takes the zone from the page's filters.timezone prop, never the machine.

Paper is chosen by stylesheet only: recapSheet.ts renders one body and swaps a4Styles()/strukStyles(), so a figure cannot appear on A4 and go missing on the 78mm roll. The roll is 78mm (80mm stock) in both modules.
