---
paths:
  - '{app/Models/Order.php,app/Models/MemberVehicle.php,app/Http/Requests/Admin/StoreOrder*.php,app/Support/VehiclePlate.php}'
---

# Support

## Plates are stored canonically by the model, never at the call site
orders.vehicle_plate and member_vehicles.plate both run through a set-Attribute calling App\Support\VehiclePlate::normalize (whitespace stripped, upper-cased), so "B 8120 DS" and "b8120ds" are one plate. Never re-normalize when writing — OrderController used to do Str::upper(Str::squish(...)) and that is now the mutator's job.

Form Requests must still normalize in prepareForValidation, because Rule::unique compares against the stored form before the model is ever touched. Migration 2026_08_29_174825 pulled existing rows in; it skips a member_vehicles row whose normalized plate collides with another, since the unique index makes that a real duplicate for a human to resolve.
