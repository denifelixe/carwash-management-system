---
paths:
  - resources/js/pages/admin/Orders.vue
---

# Resources Js Pages Admin

## One customer lookup above the Member / Non-Member tabs
The order form has a single search (id="order-customer") above the tabs; the tabs no longer carry their own member or lead pickers. It offers member vehicles (client-side), leads (the Inertia::optional leadOptions reload, live only), and — when no result owns the exact plate — a "Pelanggan baru (Non-Member)" entry. Picking switches the tab and fills it: member → pickCustomer, lead → pickLead, new → plate (or the name, if the text is not plate-shaped). Keep applyCustomerSuggestion as the only place that switches tabs from the lookup.
