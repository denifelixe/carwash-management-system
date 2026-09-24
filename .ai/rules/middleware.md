---
paths:
  - '{routes/member.php,app/Http/Middleware/EnsureMemberPortalIsAvailable.php,bootstrap/app.php}'
---

# Middleware

## The live member portal is closed by a flag, not by deleting routes
The live member portal (member.* domain) is built (Member\PortalController) and opened by MEMBER_PORTAL_ENABLED=true, which .env.example now ships. The flag stays the kill switch: EnsureMemberPortalIsAvailable ('member.portal', outermost on the routes/member.php group) renders member/UnderConstruction with a 503 for every path while it is false. config app.member_portal_enabled still defaults to false, and phpunit.xml forces it false so tests that need the portal turn it on themselves.

Do not delete the member auth routes to "clean this up". bootstrap/app.php's redirectGuestsTo/redirectUsersTo, routes/web.php, and DomainRoutingTest all resolve member.login and member.dashboard by name, and DualAuthenticationTest covers the guard end to end. Reopening the portal is one env flag; the pages behind it are untouched.

bootstrap/app.php must keep the prependToPriorityList(before: AuthenticatesRequests::class, ...) call. Laravel's priority list holds the AuthenticatesRequests *contract*, not Authenticate::class — naming the concrete class silently does nothing, and auth:member then outranks the gate and redirects member.dashboard to a login page that is itself closed.
