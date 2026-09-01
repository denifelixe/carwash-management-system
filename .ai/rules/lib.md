---
paths:
  - 'resources/js/lib/**'
---

# Lib

## Printed documents are standalone HTML in their own window, not a PDF library
The POS slip (posReceipt.ts) and the shift recaps (recapSheet.ts) are written as self-contained HTML into a window opened with window.open, with a [Cetak] toolbar hidden by @media print. No server route renders a document; do not add one. The only PDF generator in the app is the client-side one behind the slip's [Unduh PDF] button — see the section below — and recapSheet.ts still saves through the browser's own print prompt.

Handlers are wired with addEventListener after document.write, never inline <script>, so the written document carries no executable markup. openPosReceiptWindow/openRecapSheetWindow return null when the browser blocks the window; the page must flag that, not swallow it.

escapeHtml, formatWhatsapp and printedAt live in printDocument.ts and are shared by both — every value on a printed document comes from user input, so nothing reaches it raw, and there must be exactly one escaper. printedAt takes the zone from the page's filters.timezone prop, never the machine.

Paper is chosen by stylesheet only: recapSheet.ts renders one body and swaps a4Styles()/strukStyles(), so a figure cannot appear on A4 and go missing on the 78mm roll. The roll is 78mm (80mm stock) in both modules.

## The POS slip's download draws a real text PDF; print still uses the browser prompt
Supersedes the older "there is no dompdf/jsPDF … Do not add one" line in this file for the download button only.

posReceiptPdf.ts draws the slip with jsPDF's text API from the same `PosReceipt` the HTML is built from, laid out at PAGE_WIDTH (78) on one continuous page cut to the height a throwaway measuring pass reports. Do not go back to snapshotting the rendered `.paper` with html2canvas: that shipped once and gave a PDF whose text could not be selected and whose verification link could not be clicked.

So there are deliberately two layouts of one document — the HTML in posReceipt.ts and the PDF here. A new figure has to be added to both or it goes missing from the file. Only the two marks the built-in fonts cannot draw are rasterised: the QR (an SVG jsPDF cannot read) and the brand emoji, both through a canvas that returns null on a tainted cross-origin photo rather than failing the download.

The verification URL is placed with `doc.link()` under text written at `baseline: 'top'` — not `textWithLink`, which measures its rectangle off the alphabetic baseline and lands it above the text. Courier is WinAnsi, so U+2212 is swapped for a hyphen in pdfText().

One address, one place per medium: the QR prints (`.verification`, hidden by default and shown under `@media print`, so a plain Ctrl+P gets it too), the PDF spells the URL out as that clickable link, and the on-screen slip shows neither — the page is already being read at that URL. Nothing is staged on the body before printing any more; `data-output-mode` is gone and @media print decides everything the paper differs on.

jsPDF is `import()`ed from the click handler — never top-level — so the POS bundle does not carry 400KB for a button most sessions never press.

[Cetak] still goes through the window's own `print()` on both documents; do not route it through jsPDF.

Neither document can reach the app's vue-sonner toaster, so both carry the shared `.toast` (top right, `data-print-toast`), hidden by the same @media print rule as the toolbar.

Toolbar handlers hold their button in a `const` from `querySelector`, never `event.currentTarget`: they await first, and by the time they resume the click has finished dispatching and `currentTarget` is null — that silently killed the copy confirmation once. Copying also cannot assume `navigator.clipboard`, which does not exist on an outlet served over plain http; copyReceiptLink() falls back to a selection and reports failure so the toast can say so.

## Both printed documents write their own PDF; pdfDocument.ts is the shared half
Supersedes the earlier "recapSheet.ts is unchanged and still has no PDF generator" line: the recap has one now too.

pdfDocument.ts is to the PDFs what printDocument.ts is to the HTML — one copy of the mechanism, never two. It holds PdfCursor (paragraph/row/meta/line/block/gap, driven by a PageMetrics value so A4/helvetica/solid-rules and 78mm/courier/dashed come off the same code), pdfText (WinAnsi swap), pdfFileName, the colour tokens, and the raster helpers. posReceiptPdf.ts and recapSheetPdf.ts keep only their own blocks and metrics.

Each PDF module splits a pure renderPosReceiptPdf/renderRecapSheetPdf (returns the jsPDF doc) from the download that saves it — the same split renderRecapSheetDocument/openRecapSheetWindow already have, and what lets the layout be rendered and measured in Node without a browser.

A4 is a fixed page, so it breaks through cursor.ensureRoom(): a table that breaks redraws its header on the page it continues onto, and a row of summary cards is kept whole. The roll has no height and is cut to what the layout filled by a throwaway measuring pass. Verified in Node through Vite's ssrLoadModule (with ssr.external: ['jspdf']): a 60-row recap comes out 3 pages with no image XObject.

The recap PDF must not format anything — RecapSheet arrives with every figure already spelled out by the page, exactly as the HTML renderer receives it. No formatCurrency/formatDate in recapSheetPdf.ts.

The toolbar (segmented bar, glyphs, toolbarButton, toastMarkup, documentToaster) is shared from printDocument.ts. Every toolbar handler holds its button in a const from querySelector, never event.currentTarget: they await, and by then the click has finished dispatching and currentTarget is null.
