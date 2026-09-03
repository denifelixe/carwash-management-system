---
paths:
  - '{app/Models/Lead.php,app/Actions/Admin/CaptureOrderLead.php,app/Actions/Admin/MarkLeadConverted.php,app/Support/Admin/LeadQueries.php,app/Http/Controllers/Admin/LeadController.php}'
---

# Controllers Admin

## A lead is identified by its plate, and a converted lead is never deleted
Every non-member order files a lead: OrderController::store (walk-in branch) and SaveBooking (member-less branch) both call CaptureOrderLead, which resolves the row with firstOrNew(['vehicle_plate' => normalized plate]) and writes orders.lead_id. The plate is the identity, so capture is idempotent and the order form never sends a lead_id — its "cari lead lama" picker is pure prefill. A blank field at the till must not wipe what an earlier visit knew, so only non-empty values overwrite.

MarkLeadConverted stamps converted_member_id + converted_at from RegisterOrderMember (POS) and SaveMember (member module). Never delete the row: the funnel it records is the point of the module. LeadQueries::filters therefore defaults conversion to 'Belum jadi member', and searchOptions offers only un-converted, active leads.

leads.vehicle_plate and member_vehicles.plate are separate unique namespaces; StoreOrderRequest already blocks walk-in orders on a member plate, so the two never collide at order time. StoreLeadRequest/UpdateLeadRequest enforce the same rule for hand-entered leads.

The Orders page ships leadOptions as Inertia::optional so a full page load pays nothing; the walk-in tab fetches it with router.reload({ only: ['leadOptions'], data: { leadQuery } }) after a 300ms debounce. Demo\OrderController must declare the same prop (returning []) or the shared page's prop set diverges.
