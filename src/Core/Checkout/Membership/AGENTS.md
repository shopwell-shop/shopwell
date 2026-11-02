# `src/Core/Checkout/Membership` — architecture guide

Paid membership: a customer buys a **plan**, the platform collects the money through a payment channel,
and the paid membership activates the tier the plan points at.
It is the only module that sells something without a cart and without an order of the checkout.

## Layers

```
Entity/           plans (with translations), the purchase row, the subscription a purchase produces
Extension/        Store API route extension - kept at the module root, like Order/, Payment/, Gateway/
Purchase/         how a paid membership row is handed to the shared settlement kernel
SalesChannel/     `/membership/purchase` and the presenter for plan / order output
ScheduledTask/    the daily task that ends memberships whose period ran out
Service/          plan catalogue, purchase, offer summary, Subscription (open / current / expire / revoke)
Subscriber/       payment confirmed -> settlement -> subscription
```

## Three rows with three different meanings

```
membership_order  ──(one purchase, never changes)──▶  membership_subscription  ──(projection)──▶  customer_loyalty
   what was bought                                    the membership itself              membershipTierId / ValidUntil
```

- `membership_order` — what the customer bought: plan, tier, duration and price **copied at purchase time**.
  A later price change must not rewrite history, so the row never reads the plan again after creation.
- `membership_subscription` — the membership that purchase bought. One row is one uninterrupted period:
  the same tier pushes `ends_at`, another tier ends it (`end_reason = superseded`) and starts a new one.
- `customer_loyalty.membership_tier_id` / `membership_valid_until` — **a projection of the subscription, and
  `MembershipSubscriptionService` is its only writer.** The storefront, the administration and
  `TierResolver` read it without knowing that a membership exists; nothing else may write those two fields.
- the gateway's payment order — how it is collected. Its id is the membership order's own id (the `attach`
  marker points back at it), which is what makes settlement idempotent without a second key.

`MembershipPurchaseService::purchase()` writes the row, opens the payment and stores the payment number.
A failure while opening the payment marks the row `failed` instead of deleting it — an attempt that took
money must stay visible.

## Settlement is shared, not owned

A paid membership is one implementation of
`Shopwell\Core\Checkout\Payment\Storefront\Settlement\SettleablePurchase`:

- `MembershipOrderPurchase::isSettleable()` — an order without a `loyalty_tier_id` cannot activate anything.
- `MembershipOrderPurchase::settle()` — hands the locked order to `MembershipSubscriptionService::open()`,
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
  customer back to the tier their growth value earns.
- `revoke()` — ends a membership ahead of its period (refund, goodwill decision). Same fallback.
- Both are idempotent: a row that is already `ended` is left alone, so a re-run of the task is free.
- `STATE_ACTIVE` / `STATE_ENDED` only. *Why* it ended is `end_reason` (`expired`, `superseded`, `revoked`), so
  every "does this customer have a membership" query stays one equality instead of a list that grows.

## Vocabulary

| Concept | Word | Never |
|---|---|---|
| what is sold | plan (`MembershipPlan`) | package, product, sku |
| one purchase | membership order (`MembershipOrder`) | invoice |
| the membership it produces | subscription (`MembershipSubscription`) | entitlement, license, membership row |
| the tier it activates | tier (`loyaltyTierId`) | level, rank |
| plans/channels/orders shown to a customer | offer (`MembershipOfferService::forCustomer()`) | catalogue, list |

Errors come from `MembershipException`; the plan codes keep their historical `CHECKOUT__LOYALTY_PLAN_*`
values on purpose, because they are part of the Store API contract. The subscription codes follow the same
prefix for the same reason.

## Where a new capability goes

1. A new plan attribute → migration + `MembershipPlanDefinition` + `MembershipPlanEntity` + `MembershipPresenter::plan()`.
2. A new purchase rule (limit per customer, upgrade rules) → `MembershipPlanService` / `MembershipPurchaseService`,
   not the route: the route only resolves the customer and the currency.
3. A new way to *get* a membership (gift, voucher, auto-renew) → a method on `MembershipSubscriptionService`
   that describes the purchase and calls the same `subscribe()`: what a membership is must not depend on how it
   was paid for. Do not fold it into `MembershipOrderPurchase`.
4. Store API output is built by `MembershipPresenter`. A route returns `['data' => ...]` and never assembles
   the payload itself.

## Gates

- `vendor/bin/phpunit --testsuite unit --filter "Checkout\\\\Membership"` and `--testsuite integration --filter "Core\\\\Checkout\\\\Membership"`.
- `bin/console dal:validate`, `bin/console lint:container` after touching `DependencyInjection/membership.php`.
- The settlement path is only proven end to end: a unit test of the subscriber does not prove the tier was activated.
- A new row means the test database needs the migration: `bin/console -e test database:migrate --all`
  (with `DATABASE_URL` pointing at `<db>_test`, the bootstrapper does not re-run migrations on an existing DB).
