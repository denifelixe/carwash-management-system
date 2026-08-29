---
paths:
  - 'database/migrations/**'
---

# Migrations

## Use dateTime()/datetimes(), never timestamp()/timestamps()
The app stores the outlet's local wall clock (see Master > Timezone). MySQL TIMESTAMP cannot hold that: it converts on write and again on read using each connection's session time_zone, so the same row reads 04:15 through the app and 11:15 through a client whose session is +07:00. MySQL DATETIME does no conversion at all — literal in, literal out, for every client.

Every business column was converted for this reason. New migrations must use $table->dateTime('paid_at') and $table->datetimes(), not timestamp()/timestamps().

Do not set a 'timezone' key on the mysql connection in config/database.php to paper over a shift — pinning the session to +00:00 is what caused the 7-hour gap in the first place, and with DATETIME columns the session zone is irrelevant. The one exception left is the framework's own failed_jobs.failed_at, which only the app ever reads.
