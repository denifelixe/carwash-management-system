---
paths:
  - '{app/Http/Controllers/Admin/**,app/Actions/Admin/**,app/Support/Admin/**}'
---

# Support Admin

## Store datetimes on the outlet clock, and read them back untouched
The app timezone is whatever Master > Timezone holds; AppServiceProvider::boot
applies it before anything runs, so a datetime column receives local wall clock.

Write now(). Never now('Asia/Jakarta') or any other hardcoded zone — App\Support\Timezones::ZONES is the only place a zone identifier belongs, and AppSettings::timezone() the only way to ask which one is live.

Reading a day back is a plain date lookup: no ->timezone() on the way out, no ->utc() on a query bound. See FinanceQueries::posTransactionsForDate, which keeps a midnight-to-midnight range only so the ['order_id', 'paid_at'] index still carries the scan.
