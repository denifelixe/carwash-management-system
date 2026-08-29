---
paths:
  - 'app/Support/Admin/**'
---

# App Support Admin

## Datetime columns hold the outlet's wall clock, never UTC
The outlet's clock is the storage format. This replaces the old "store UTC
instants, read them back in Jakarta" contract, which is gone.

AppSettings::applyTimezone() pushes the configured zone (app_settings.timezone, set in Master > Timezone) into PHP's default timezone as the first line of AppServiceProvider::boot. From then on now(), the model datetime casts, and every presenter already speak the outlet's local time.

So: write now(), never now($tz). Read $model->paid_at->format(...) with no ->timezone() call. Bound a day with CarbonImmutable::parse($date)->startOfDay() and no ->utc() — the lookup is just the date. Never hardcode a zone identifier; App\Support\Timezones::ZONES is the only place they are written down.

The three JS optimistic timestamps (Finance.vue, Pos.vue, posReceipt.ts) get the zone from the filters.timezone prop and must pass it as Intl.DateTimeFormat's timeZone, or a cashier on a differently-zoned laptop sees a row the server disagrees with.
