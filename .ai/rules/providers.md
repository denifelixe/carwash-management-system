---
paths:
  - '{config/auth.php,app/Support/Auth/**,app/Providers/AppServiceProvider.php}'
---

# Providers

## is_active is enforced by the user provider, not by login
Both guards use the `active_eloquent` provider driver (registered in AppServiceProvider::configureAuthentication), backed by App\Support\Auth\ActiveUserProvider. It overrides newModelQuery() with `where('is_active', true)`, which is the single funnel for retrieveById, retrieveByToken and retrieveByCredentials — so a deactivated admin or member is refused on a live session, a remember-me recall, and a fresh login alike. The `'is_active' => true` in AdminLoginRequest/MemberLoginRequest::credentials() is now redundant but harmless; do not "clean it up" by moving the check back into the login path only, that was the original hole.

When a status flips to inactive, controllers also call App\Support\Auth\AccountSessions::revoke() to null remember_token and delete that account's rows from the sessions table (columns are admin_id/member_id, not user_id).
