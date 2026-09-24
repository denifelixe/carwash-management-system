---
paths:
  - '{app/Http/Controllers/Member/**,app/Http/Controllers/Demo/MemberController.php,app/Support/Member/**,resources/js/pages/member/**,resources/js/layouts/member/**,resources/js/composables/useMemberPortalRoutes.ts}'
---

# Composables

## Demo and live member portal share one set of pages
resources/js/pages/member/{Dashboard,Stamps,Services,Rewards,Profile}.vue and layouts/member/MemberLayout.vue are rendered by both Demo\MemberController (mode 'demo', fixtures) and Member\PortalController (mode 'live', MemberPortalQueries). There is no demo/member copy. Both send the same prop set.

Live sends [] for notifications, promos and vouchers, and null for referralCode; the pages hide those sections when they are empty. Links go through memberPortalUrls(mode).

Wayfinder URLs carry the domain, so compare active tabs using urlPath(). The portal stays read-only: its only POST routes are login and logout, and MemberPortalPagesTest asserts that. Members get portal access when an admin sets an email and password in the member form. A blank password on edit keeps the current one, and a member who has a password must keep an email.
