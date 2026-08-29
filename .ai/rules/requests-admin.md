---
paths:
  - '{app/Actions/Admin/RegisterOrderMember.php,app/Models/Member.php,app/Http/Requests/Admin/StoreOrderMemberRequest.php}'
---

# Requests Admin

## A member registered at the till has no portal credentials
members.email and members.password are nullable: the cashier signs a walk-in up with a name, phone, and car, and never an email. Those two columns are portal credentials, filled in only if that person asks to sign in. A null password cannot be logged in with (the hasher returns false for it), so nothing needs guarding — but never assume a Member has either value. OrderPresenter::customer reads hasAccount off $member->password.

RegisterOrderMember moves the order onto the new member rather than copying it, so payments booked before and after both belong to them, and it recomputes stamps_earned from the order_services pivot — a walk-in order is written with 0 because there was nobody to hold the stamps.
