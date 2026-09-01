---
paths:
  - '{app/Http/Controllers/Admin/RecapQrController.php,app/Support/Admin/RecapLink.php,resources/js/lib/recapSheet*.ts}'
---

# Js Lib

## A recap's link is a deep link into the console, not a public page
Unlike a receipt, a recap has no signed guest page and must not get one casually: its figures are the outlet's daily takings, so anyone holding a public URL would see them. The address a printed recap carries is admin.finance.index / admin.pos.index with `date` and `shift`, which still asks whoever follows it to log in.

RecapLink is the only place that address is built. RecapQrController takes a page key plus the two filters and assembles the URL itself — never a destination from the caller — so the endpoint cannot be used to put an arbitrary address behind the outlet's domain. It is gated by the console it points at (admin.finance.read / admin.pos.read), not by one blanket ability.

The two consoles do not share a shift alphabet: Finance keys tabs by work shift id ('all' / 'tanpa-shift' / id), the POS by the master list's slug ('total' / 'tanpa-shift' / key). RecapLink::shift() therefore bounds the shape ([a-z0-9-]{1,64}) rather than checking a list.

The shift tab is client state — switching it never hits the server — so both pages read it back off `window.location.search`, not props.filters. That is what makes a printed recap's link land on the shift it was taken from.

The QR is encoded on request because the address does not exist until the desk has picked a day and a tab; the receipt's is rendered with the slip because the server knows it up front. Wayfinder returns protocol-relative URLs and the recap document lives in about:blank, which has no scheme to resolve them against — hence absoluteUrl() in recapSheet.ts.

Screen shows neither QR nor link (the toolbar has a Salin Link button); paper and the PDF carry both, since neither has a button to press.
