---
paths:
  - '{app/Http/Controllers/Admin/RewardController.php,app/Http/Controllers/Demo/RewardController.php,app/Support/Admin/RewardQueries.php,app/Support/Admin/RewardPresenter.php,resources/js/pages/admin/Rewards.vue}'
---

# Admin Support Admin Js Pages Admin

## Reward catalog is unpaginated; redeemed rewards are deactivated, not deleted
The catalog is a short hand-kept list, so it ships whole and the page searches and filters it client-side. Only the redemption history is paginated, with pageName redemptionPage.

"Customer memenuhi syarat" counts come from the stampBalances prop (the wallet balance of each active member), never a hardcoded threshold.

destroy refuses with 422 once any redemption references the reward. Owners deactivate it instead, so the history keeps its link.

Demo\RewardController must send the same props: rewards, redemptions, stats, stampBalances, categories, serviceOptions, filters, capabilities.
