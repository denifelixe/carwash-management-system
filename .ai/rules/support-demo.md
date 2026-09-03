---
paths:
  - '{resources/js/lib/posReceipt*.ts,resources/js/lib/pdfDocument.ts,app/Support/AppSettings.php,app/Support/Demo/Brand.php}'
---

# Support Demo

## The slip is dressed from brand.receipt, never brand.name or brand.photo
Master > Struk carries the slip's own dressing: business name, footer note, logo image, printed logo width, and the print-logo / print-QR switches. Both slip layouts (posReceipt.ts HTML and posReceiptPdf.ts) read them off `brand.receipt`, never the top-level brand — `brand.name` and `brand.photo` stay the app's, for the console and the recap sheet, so re-marking the roll must not re-mark the shell. The PDF passes the mark explicitly: `brandArtwork(win, brand, brand.receipt.photo)`; the recap keeps the default `brand.photo`.

The mark is sized in millimetres up to the roll's full 72mm printable area, and both layouts must agree: the HTML sets `max-width: ${logoWidth}mm` (which is why receiptStyles() takes the brand), the PDF passes the same number to drawBrandMark. Both cap height at width * 0.71, the aspect the old fixed 56x40px box gave it.

Four traps:
- `receiptPhotoUrl()` falls back to `appPhotoUrl()` when unset, so removing the slip's logo hands it back to the app photo rather than blanking it. `hasOwnReceiptPhoto()` is what tells the two apart, and it goes through brand() so the demo stays on shipped defaults.
- `receiptLogoWidth()` clamps on read as well as validating on write — a width stored under an older range must still print inside the roll — and an omitted `receipt_logo_width` in the request keeps the saved size rather than resetting it.
- The verification QR is a setting (`brand.receipt.showQr`, default off). The old hardcoded `.verification { display: none }` inside @media print is gone; print is `display: block` and the block is simply not written when the switch is off. Do not reintroduce a hardcoded hide for a print test — flip the setting.
- `receiptFooterNote()` falls back to the default only for an unset key. An outlet that saves it blank prints no fine print at all, so never coalesce '' to the default.
