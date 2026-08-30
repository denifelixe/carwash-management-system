---
paths:
  - '{routes/member.php,app/Http/Middleware/EnsureMemberPortalIsAvailable.php,bootstrap/app.php}'
---

# Middleware

## The live member portal is closed by a flag, not by deleting routes
The live member portal (member.* domain) is unbuilt. EnsureMemberPortalIsAvailable ('member.portal', outermost on the routes/member.php group) renders member/UnderConstruction with a 503 for every path until MEMBER_PORTAL_ENABLED=true (config app.member_portal_enabled, default false).

Do not delete the member auth routes to "clean this up". bootstrap/app.php's redirectGuestsTo/redirectUsersTo, routes/web.php, and DomainRoutingTest all resolve member.login and member.dashboard by name, and DualAuthenticationTest covers the guard end to end. Reopening the portal is one env flag; the pages behind it are untouched.

bootstrap/app.php must keep the prependToPriorityList(before: AuthenticatesRequests::class, ...) call. Laravel's priority list holds the AuthenticatesRequests *contract*, not Authenticate::class — naming the concrete class silently does nothing, and auth:member then outranks the gate and redirects member.dashboard to a login page that is itself closed.
