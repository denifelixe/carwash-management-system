---
paths:
  - resources/js/components/admin/ServiceCartPicker.vue
---

# Components Admin

## The cart picker collapses by CSS breakpoint, not by a JS media query
ServiceCartPicker renders two panels (catalog, cart) as an accordion driven by `openPanel: 'services' | 'cart' | null`. Collapsing is phone-only: a collapsed body gets `hidden sm:block`, the catalog header is `sm:hidden`, and the cart header is `sm:pointer-events-none`, so from `sm` up both panels are always open and the desktop layout is unchanged. Do not add a matchMedia composable for this — the project has none and the CSS form avoids an SSR/hydration mismatch.

The cart header is visible on the first screen because the catalog box is short on phones (`max-h-40 sm:max-h-64`), not because the cart floats: it stays in normal flow under the catalog. A sticky/overlay cart bar was tried and rejected — keep the cart in flow and buy the space back from the catalog height instead.

Because the picker now owns the "Pilih layanan" header on phones, Orders.vue and Bookings.vue hide their own uppercase "Layanan" label with `hidden ... sm:block`. Keep those two in sync with the header here.
