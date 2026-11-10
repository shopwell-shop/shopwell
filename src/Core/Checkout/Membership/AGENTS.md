# `src/Core/Checkout/Membership` — architecture guide

Paid membership: a customer buys a **plan**, the platform collects the money through a payment channel,
and the paid membership activates the tier the plan points at.
It is the only module that sells something without a cart and without an order of the checkout.

## Layers

```
Entity/           plans (with translations), the purchase row, the subscription a purchase produces
Extension/        Store API route extension - kept at the module root, like Order/, Payment/, Gateway/
Purchase/         how a paid membership row is handed to the shared settlement kernel
SalesChannel/     `/membership` (read: plans, channels, past purchases, running subscription), `/membership/purchase`, the presenter
ScheduledTask/    the daily task that ends memberships whose period ran out
Service/          plan catalogue, purchase, offer summary, Subscription (open / current / expire / revoke)
Subscriber/       payment confirmed -> settlement -> subscription
```

## Two routes: one page reads, one page buys

`MembershipRoute` (`store-api.membership`) is the read side of `/account/membership`; it is the counterpart
of `Loyalty/SalesChannel/LoyaltyRoute`, and they are deliberately two payloads rather than one.

- `plans` / `channels` / `orders` come from `MembershipOfferService::forCustomer()` — the offer is one call
  and one presenter, not a query per section.
- `subscription` comes from `MembershipSubscriptionService::current()` and is `null` when the customer holds
  none. **Never** build that block out of `loyalty_account.paid_tier_id / membership_paid_until`:
  the projection exists so the rest of the platform can answer "which tier applies" without knowing that
  memberships exist. It carries no start date, no plan name and no amount, and the page shows all three.
- `planName` is copied from the subscription row, not joined from the plan. A plan renamed later must not
  rewrite what the customer agreed to — the same reason `membership_purchase` copies the price.
- The read route is what the page polls after a purchase (`/account/membership/json`); it does not get a
  second endpoint for "the order I just opened".

**A new `SalesChannel/*Route.php` is not registered by being annotated.** Every checkout module's route
directories are imported one by one in `src/Core/Checkout/Resources/config/routes.php`. The membership
directory was missing from that list from the beginning, so `/membership/purchase` existed as a service
and never reached the router — invisible, because the storefront calls the route object, not HTTP. Adding
this read route is what surfaced it. Prove a route with `bin/console debug:router | grep <route name>`,
never with `lint:container`: the container is happy either way.

## Three rows with three different meanings

```
membership_purchase  ──(one purchase, never changes)──▶  membership_subscription  ──(projection)──▶  loyalty_account
   what was bought                                    the membership itself              paidTierId / membershipPaidUntil
```

- `membership_purchase` — what the customer bought: plan, tier, duration and price **copied at purchase time**.
  A later price change must not rewrite history, so the row never reads the plan again after creation.
- `membership_subscription` — the membership that purchase bought. One row is one uninterrupted period:
  the same tier pushes `ends_at`, another tier ends it (`end_reason = superseded`) and starts a new one.
- `loyalty_account.paid_tier_id` / `membership_paid_until` — **a projection of the subscription, and
  `MembershipSubscriptionService` is its only writer.** The storefront, the administration and
  `TierResolver` read it without querying the subscription; nothing else may write those two fields.
  `TierResolver` alone writes the effective `tier_id`, taking the higher of earned and valid paid tiers.
  Free tier points continue accumulating during a paid membership.
- the gateway's payment order — how it is collected. Its id is the membership purchase's own id (the `attach`
  marker points back at it), which is what makes settlement idempotent without a second key.

`MembershipPurchaseService::purchase()` writes the row, opens the payment and stores the payment number.
A failure while opening the payment marks the row `failed` instead of deleting it — an attempt that took
money must stay visible.

## Settlement is shared, not owned

A paid membership is one implementation of
`Shopwell\Core\Checkout\Payment\Storefront\Settlement\SettleablePurchase`:

- `MembershipPurchase::isSettleable()` — a purchase without a `loyalty_tier_id` cannot activate anything.
- `MembershipPurchase::settle()` — hands the locked purchase row to `MembershipSubscriptionService::open()`,
  stores the period that purchase bought on the row (`ends_at - duration_days`, because a renewal extends a
  membership that may have started long before) and marks it `activated`. **This module never writes the tier
  itself** — the validity math (same tier continues, another tier starts now, a ran-out membership does not
  continue) lives in `MembershipSubscriptionService` and must not be copied here.
- `PurchaseSettlement` runs the transaction: lock, short-circuit when already settled, refuse on amount mismatch.
  Do not add a second "did we already activate this" flag — the row state is the flag.

`MembershipPurchaseSettlementSubscriber` listens to `System\Payment\Order\Event\PaymentOrderPaidEvent` — the one
event both the channel notification and the reconciliation of open payments end in — and hands its payment no to
the settlement.
A payment of the shop without a membership attach marker is ignored.
A redelivered notification must be a no-op, not a second month of membership.

## The membership ends as an act, not as a date

`TierResolver` still checks the date when it reads a tier, so a customer stops being a member the moment the
period ends — but the end is also written down:

- `MembershipSubscriptionService::expireDue()` — the daily `MembershipExpirationTask` ends what ran out
  (`state = ended`, `end_reason = expired`), clears the projection and lets `TierResolver::resolve()` fall the
  customer back to the tier their tier points earn.
- `revoke()` — ends a membership ahead of its period (refund, goodwill decision). Same fallback.
- Both are idempotent: a row that is already `ended` is left alone, so a re-run of the task is free.
- `STATE_ACTIVE` / `STATE_ENDED` only. *Why* it ended is `end_reason` (`expired`, `superseded`, `revoked`), so
  every "does this customer have a membership" query stays one equality instead of a list that grows.

## Vocabulary

| Concept | Word | Never |
|---|---|---|
| what is sold | plan (`MembershipPlan`) | package, product, sku |
| one purchase | membership purchase (`MembershipPurchase`) | invoice, membership order |
| the membership it produces | subscription (`MembershipSubscription`) | entitlement, license, membership row |
| the tier it activates | tier (`loyaltyTierId`) | level, rank |
| plans/channels/orders shown to a customer | offer (`MembershipOfferService::forCustomer()`) | catalogue, list |

Errors come from `MembershipException`; the plan codes keep their historical `CHECKOUT__LOYALTY_PLAN_*`
values on purpose, because they are part of the Store API contract. The subscription codes follow the same
prefix for the same reason.

## `purchase` in the code, "order" on the screen

The table is `membership_purchase` and its entity is `MembershipPurchase`, but the storefront and the
administration still say "order" in the sentences (`account.membershipOrdersTitle`). That is
deliberate, not a leftover:

- the row is **not** an `order` in the DAL sense - there is a real `order` table right next to it, and
  `membership_order` read as if the two were related. Inside the code, `purchase` is the only correct word.
- to the customer, what they bought **is** an order. Snippet keys are also theme / plugin override points,
  so renaming them silently drops the wording a shop already replaced.

Rule of thumb: **rename the code, not the sentence.** If a snippet key says `order`, leave it - unless you
are also prepared to migrate every theme.

The page, its component and its menu key follow the code, not the sentence: `sw-membership-purchase-list`,
route `sw.membership.purchases` in the dedicated module `sw-membership`, navigation id and
`CoreAdminMenuProvider` internal key `sw-membership-purchases`. The orders queue is its own module on
purpose: a route of the settings module with `parentPath: 'sw.order.index'` highlighted both entries of
the orders menu at once, and the settings module only maintains tiers and plans. The twig blocks of that
page are still named `sw_loyalty_order_list*` - a block name is what a plugin extends, so it is not
renamed along with the directory.


## Where a new capability goes

1. A new plan attribute → migration + `MembershipPlanDefinition` + `MembershipPlanEntity` + `MembershipPresenter::plan()`.
2. A new purchase rule (limit per customer, upgrade rules) → `MembershipPlanService` / `MembershipPurchaseService`,
   not the route: the route only resolves the customer and the currency.
3. A new way to *get* a membership (gift, voucher, auto-renew) → a method on `MembershipSubscriptionService`
   that describes the purchase and calls the same `subscribe()`: what a membership is must not depend on how it
   was paid for. Do not fold it into `MembershipPurchase`.
4. Store API output is built by `MembershipPresenter`. A route returns `['data' => ...]` and never assembles
   the payload itself.

## Gates

- `vendor/bin/phpunit --testsuite unit --filter "Checkout\\\\Membership"` and `--testsuite integration --filter "Core\\\\Checkout\\\\Membership"`.
- `bin/console dal:validate`, `bin/console lint:container` after touching `DependencyInjection/membership.php`.
- `bin/console debug:router` after adding a route file, and after adding a route to an existing one. A missing
  entry in `src/Core/Checkout/Resources/config/routes.php` produces no error anywhere else.
- The settlement path is only proven end to end: a unit test of the subscriber does not prove the tier was activated.
- A new row means the test database needs the migration: `bin/console -e test database:migrate --all`
  (with `DATABASE_URL` pointing at `<db>_test`, the bootstrapper does not re-run migrations on an existing DB).
