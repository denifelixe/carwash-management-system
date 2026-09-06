---
paths:
  - '{resources/js/components/admin/PlateInput.vue,resources/js/lib/vehiclePlate.ts}'
---

# Admin Js Lib

## Every plate is typed in the shared three-column field
A number plate is never entered through a plain <input>. resources/js/components/admin/PlateInput.vue renders three separate boxes — letters, digits, letters (2/4/3 characters, from plateSegmentLengths) — and is used by Orders, Pos, Bookings, Customers, Leads and the member registration page. Add a new plate field with it, not with a text input.

The column keeps only the characters it accepts and carries the rest into the next one, so "b8120ds" typed straight through lands in all three; a full column jumps ahead on its own and Backspace at the head of a column eats the last character of the one before it. Everything is upper-cased as it is typed.

The v-model value stays canonical — "B8120DS", no spaces, exactly what App\Support\VehiclePlate::normalize stores — so pages keep comparing with normalizePlate() and displaying with formatPlate(). splitPlate() in resources/js/lib/vehiclePlate.ts is the one place a plate is cut into columns; it is greedy per column and drops what cannot belong rather than shifting it along.
