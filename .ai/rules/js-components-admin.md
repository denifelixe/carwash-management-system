---
paths:
  - '{app/Support/Admin/TransactionShiftResolver.php,resources/js/components/admin/AdminSessionDialogs.vue}'
---

# Js Components Admin

## "Tanpa shift" admins skip the login shift popup
An admin with shift_mode=fixed and shift_id=null ("Tanpa shift" on the Users page) does not use shifts: loginPresentation() reports pending=false so the login popup never opens, and resolve() stamps null. Fixed with an assigned shift, and every schedule-mode admin (even with zero matching windows at login), still confirm once per login. There is no separate 'none' shift_mode — do not add one.
