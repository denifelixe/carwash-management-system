# Context: Reward module, stamp wallet, and live member portal

This is a handoff for AI agents picking up this work in the Laravel + Inertia v3 (Vue) carwash app. Read `CLAUDE.md` and `.ai/rules/index.md` before editing anything; the rules recorded during this work are listed at the end.

Session date: 2026-09-24. None of this work is committed yet.

The working tree also contains the owner's own unrelated changes, which were already staged before this session: cash deposits (`RecordCashDeposit`, `StoreCashDepositRequest`, `CashEntryAttachments`, Finance.vue, and the `transfer_reference` migration). Do not revert them, and do not mix them into a reward commit without asking.

## What was asked

1. Build the **Reward module** in the admin panel. Until now it existed only as a demo mockup.
2. **Activate the member app** (the member portal on the `member.*` domain).
3. An order that is **Selesai** must no longer allow its customer or member to be changed.

## Decisions made with the owner

| Topic | Decision |
|---|---|
| Stamp balance | **Wallet**: stamps earned minus stamps redeemed. The old `lifetime % stampTarget` card model is gone. |
| When stamps are credited | Only from orders with status **`selesai`** (paid in full and settled). |
| Redemption | The admin keeps the reward catalog; the **cashier redeems at the POS** as a discount on the order. **One reward per order. No vouchers.** |
| Member login | Stays **email + password**. The **admin sets them** in the Member form. There is no self-registration. |
| Customer lock | An order that is `selesai` **or** has a reward redemption cannot change its customer. The handler (petugas) can still be edited. |

## Data model (new migrations, dated 2026_09_24_13243x)

- **`rewards`**: `name`, `description`, `icon`, `category`, `required_stamps`, `stock`, `is_active`, datetimes.
- **`reward_service`**: pivot linking a reward to the services it pays for.
  - If a reward has **no services**, it is **merchandise**: it can be redeemed on any member order and takes 0 off the bill.
- **`reward_redemptions`**: the debit side of the wallet.
  - Columns: `member_id`, `order_id` (unique), `reward_id` (nullable), `redeemed_by_admin_id`.
  - Snapshots copied at redemption: `reward_name`, `stamps`, `discount`. Also `redeemed_at`.
  - Soft deletes via a `deleted_at` dateTime.
- New models `Reward` and `RewardRedemption`, each with a factory (`inactive()` and `outOfStock()` states).
- New relations: `Member::rewardRedemptions()` and `Order::rewardRedemption()`.
- New method `Order::isCustomerLocked()`.
- `reward_redemptions` was added to `ClearOperationalData::TABLES`.

## Core logic

**Stamp wallet** — [app/Support/Admin/MemberStamps.php](app/Support/Admin/MemberStamps.php). This is the single source for balances.
- Balance = `SUM(orders.stamps_earned WHERE status='selesai')` − `SUM(reward_redemptions.stamps)`.
- `withBalances()` adds the aggregates to a member query; `balance()`, `earned()`, `redeemed()`, `rewardsClaimed()`, and `circulating()` read them.
- `history()` merges earn and redeem entries into one ledger. Its ids are strings: `order-N` / `redeem-N`.
- `OrderQueries::withMemberAggregates()` uses it.
- `OrderPresenter::customer()` now returns `stamps` (the balance), `lifetimeStamps`, `redeemedStamps`, and `rewardsClaimed`.

**Redemption rules** — [app/Support/Admin/RewardRedemptionRules.php](app/Support/Admin/RewardRedemptionRules.php).
- `discountFor()` returns one unit of the cheapest order service that the reward covers. The caller caps it at the amount due.
- `refusal()` returns an Indonesian error message for each failing check:
  - the order has no member
  - the order already has a reward
  - the reward is inactive
  - the reward has no stock left
  - the member's balance is too low
  - no service in the order is covered by the reward

**POS payment flow:**
- `StoreOrderPaymentRequest` accepts `reward_id`. **`discount` now holds only the cashier's own discount**; the reward discount is always computed on the server.
- `after()` checks the rules without locks, so the cashier gets a field error.
- `RecordOrderPayment::redeemReward()` checks the rules again with the reward and member rows locked. It then:
  - decrements the reward's stock
  - creates the redemption row
  - adds the reward discount to the order's discount
  - sets `orders.reward_name`
- If the reward clears the whole bill, the payment channel is labelled `'Reward'`.

**Order deletion and payment deletion:**
- `DeleteOrder` voids the order's redemption (soft delete) and restores the reward's stock.
- Deleting a payment transaction does **not** revert the reward, the same way it does not revert a cashier discount.

**Order edit lock:**
- `UpdateOrderRequest` drops the customer validation rules when the order is locked.
- `UpdateOrder` keeps the stored customer, but still saves the handler.
- When the order's services are locked (it has payments) but the customer is still editable, `stamps_earned` is recalculated from the pivot. A walk-in customer gets 0.

## Admin Reward module

The page graduated from the demo following `.ai/rules/admin.md`: one Vue page shared by demo and live.
- **Page**: [resources/js/pages/admin/Rewards.vue](resources/js/pages/admin/Rewards.vue), moved from `pages/demo/admin/`. It branches on `mode: 'demo' | 'live'` and takes `capabilities {create, update, delete}`.
  - The catalog is **not paginated**; search and filtering happen on the page.
  - Only the redemption history is paginated, under `redemptionPage`.
  - The form gained a "Layanan yang berlaku" (applicable services) multi-select.
- **Live controller**: `Admin\RewardController` with index, store, update, updateStatus, and destroy.
  - `destroy` returns 422 if the reward was ever redeemed; owners deactivate it instead.
- **Demo controller**: `Demo\RewardController` sends the same props. Its redemption fixtures are in `Catalog::rewardRedemptions()`.
- **Shared props**: `rewards, redemptions, stats, stampBalances, categories, serviceOptions, filters, capabilities`.
- **Supporting classes**: `SaveReward`; `StoreRewardRequest`, `UpdateRewardRequest`, `UpdateRewardStatusRequest`; `RewardQueries`; `RewardPresenter`.
- **Routes** (`routes/admin.php`): `reward` → `admin.rewards.index` / `admin.rewards.store`; `reward/{reward}` → `admin.rewards.update` / `admin.rewards.destroy`; `reward/{reward}/status` → `admin.rewards.status.update`.
- **Wiring**:
  - `'rewards'` was added to the Gate loop in `AppServiceProvider`.
  - `AdminShell::moduleEntry()` maps `'rewards'` to `admin.rewards.index`.
  - The module is already seeded in `admin_modules`; the manager role has create/read/update on it.
- **Placeholders replaced with real data**:
  - `PosController` and `MemberController` now send `rewards` from `RewardQueries::activeCatalog()` instead of `[]`.
  - The dashboard card "Stempel Ditukar" now reads `RewardQueries::redeemedOnDate()`.
- **POS page** (`Pos.vue`, live mode) posts `reward_id` plus `discount = cashierDiscount`. `redeemableRewards` now also allows merchandise rewards.

## Member access

- `StoreMemberRequest` and `UpdateMemberRequest` gained `password`: nullable, min 8, confirmed.
  - On create, email is required when a password is given.
  - On update, email is required when a password is given **or** the member already has one.
- `SaveMember` writes the password only when it is filled in. `MemberController@update` revokes the member's sessions when the password changes.
- The Customers.vue member form gained an "Akses portal member" section (password + confirmation).

## Live member portal

- `.env` and `.env.example` now set `MEMBER_PORTAL_ENABLED=true`. **Production also needs this set.**
  - The config default is still `false`.
  - `phpunit.xml` forces it to `false`; tests that need the portal turn it on themselves.
- **Shared pages**: `resources/js/pages/member/{Dashboard,Stamps,Services,Rewards,Profile}.vue` and `layouts/member/MemberLayout.vue`.
  - These were moved from `pages/demo/member` and `layouts/demo`; the old placeholder live Dashboard and layout were deleted.
  - `app.ts` gives every `member/*` page the one `MemberLayout`.
- **Controllers**:
  - Live: `Member\PortalController`, reading data from `app/Support/Member/MemberPortalQueries.php`.
  - Demo: `Demo\MemberController` now renders `member/*` with `mode: 'demo'`.
- **Routes** (`routes/member.php`, all GET and all read-only): `dashboard`, `stempel`, `layanan`, `reward`, `profil`. The route names are `member.dashboard/stamps/services/rewards/profile`.
- **Live-only differences**:
  - `notifications`, `promos`, and `vouchers` are sent as `[]`, and `referralCode` as `null`. The pages hide those sections.
  - The Profile page's "Keluar" button posts the `logout` form.
- **Links**: [resources/js/composables/useMemberPortalRoutes.ts](resources/js/composables/useMemberPortalRoutes.ts) provides `memberPortalUrls(mode)` and `urlPath()`. Wayfinder URLs include the domain, so compare tabs by path.

## Test and tooling notes

- **New test files**: `AdminRewardTest`, `PosRewardRedemptionTest`, `MemberPortalPagesTest`.
- **Tests added to existing files**: the customer-lock tests at the end of `AdminOrderTest`.
- **Existing tests updated**: `AdminMemberTest` (now uses wallet semantics), `AdminOrderTest` (a `selesai` order keeps its customer), `VehiclePlateValidationTest` (its member fixture has no email or password), `VehiclePlateFormattingTest` (component paths and the Orders.vue count, now 11), `Demo/AdminModulesTest`, and `Demo/MemberPortalTest`.
- `phpunit.xml` sets `memory_limit=512M`; the full suite exceeded 128M in one process.
- **Last full run**: 1149 tests passing, with `vue-tsc`, eslint, prettier, and `npm run build` all clean.
- **Shell pitfall in this environment**: Git Bash mangles backslashes in inline `sed`/`perl` for PHP namespaces. Use the Edit tool for namespaced code. `pint` must be run as `php vendor/bin/pint --dirty --format agent`.

## Not done / possible follow-ups

- There is no loyalty card on the Reports page yet. If one is added, read the figures from `RewardQueries` / `MemberStamps`.
- Member notifications, promos, referral bonuses, vouchers, and the member QR card do not exist in live.
- There is no self-registration or password reset for members; the admin sets the password.
- The demo Customers module still uses its own `% stampTarget` fixture maths; it was not converted to the wallet model.
- Nothing has been checked in a browser; only the automated tests were run.

## Rules recorded (in `.ai/rules`)

- `admin-actions-admin-js-pages-admin.md`: the stamp wallet, the server-computed reward discount, and the double check.
- `admin-support-admin-js-pages-admin.md`: the reward catalog is unpaginated; redeemed rewards are deactivated, not deleted.
- `composables.md`: demo and live member portal share pages.
- `models-js-pages-admin.md`: a settled or rewarded order keeps its customer.
- Updated: `middleware.md` (the portal flag), `support-admin-js-pages-admin.md`, and `admin-support-admin.md` (the old "reward module not live" notes).
