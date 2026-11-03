# `src/Core/Checkout/Loyalty` — architecture guide

The points program: every customer owns one **loyalty account** from the moment the customer exists,
points are booked into it as an append-only ledger, and the accumulated tier points decide the customer's tier.
This module also owns the tier table the paid-membership module activates against.
A paid membership is not this module: it lives in `Checkout\Membership` and only projects its tier onto the
loyalty account (the tier table stays here, because a tier is earned by points and bought by membership alike).

## Layers

```
Api/              admin endpoints (read the balance, adjust points)
Cart/             turns a requested redemption into a credit line item on the cart
Entity/           the account, its point transactions, the batches behind the balance, the tiers, their benefits and translations, the redemption ladder
Event/            points awarded / redeemed, tier changed
Extension/        Store API route extensions - kept next to the module like Order/, Payment/, Gateway/ do
SalesChannel/     `/loyalty` (the rewards page read), `/loyalty/redeem` (request and response classes live beside the route)
ScheduledTask/    daily point expiration
Service/          account lifecycle, ledger, point rules, settings, tier resolution
Subscriber/       account provisioning on customer write, order state -> award / redeem, wallet top-up -> bonus, closed return -> revoke
```

`Extension/` stays where it is.
A `*RouteExtension` is not a route: it carries the request fields and the route registration of a
`SalesChannel/` route, and every other checkout module keeps it at the module root.

## `/loyalty` answers only what was earned

The rewards page is one of two account pages. This route serves the half a customer **earned**: balance,
what a point is worth, the ladder, the tier list. Everything a customer **buys** — plans, the running
subscription, the membership orders — is served by `Membership\SalesChannel\MembershipRoute`, and the
storefront renders it on `/account/membership`.

The split is not cosmetic: a shop that sells no paid membership should not run a single membership query
when a customer opens the rewards page. If you are about to add a field here, ask whether a customer who
never paid for anything would still see it. If not, it belongs in the membership route.

- The two routes are two payloads, not one payload cut in half. Do not "reuse" the offer by injecting
  `MembershipOfferService` back into this class.
- The route also states the **earning rules** (`pointsPerCurrencyUnit`, `tierPointsPerCurrencyUnit`,
  `tiers[].pointsMultiplier`), because the page has to print them. A rate typed into a template becomes a
  lie the day the shop changes it.
- `tierId` is read here, and that is deliberate: it is the projection, i.e. "which tier applies
  right now", whoever wrote it. The subscription period is **not** read from the projection — it has no
  start, no plan name and no amount. See `Membership/AGENTS.md`.

## The balance is a projection

`loyalty_account.points_balance` is derived from `loyalty_point_transaction`.
It is written **only** by `LoyaltyLedger::bookPoints()`, which locks the account row, writes the transaction and
moves the balance in the same database transaction.
Nothing else may update `pointsBalance` — not a subscriber, not a route, not an admin endpoint.

- `LoyaltyLedger` — the write side: `bookPoints()`, `expireLots()`, `giveBackPoints()`, `revokeOrderPoints()`,
  `addTierPoints()`. It does **not** grant
  memberships: `tier_id` / `membership_paid_until` are a projection of `Checkout\Membership` and
  only `MembershipSubscriptionService` writes them.
  `addTierPoints()` carries the name of the column it writes; the concept is *tier points* and the rename
  (`tierPoints`) is planned together with the column, not before it.
- `LoyaltyAccountService` — the account lifecycle: `account()`, `balance()`, `open()`.
  `open()` is idempotent and relies on `UNIQUE(customer_id)`; it treats losing the creation race as success.
  Never `create()` an account anywhere else — the unique key would reject it and the caller would book into nothing.
- `LoyaltyPointService` — the rules: `award()` / `redeem()` / `expireDue()` / `adjust()` / `addTierPoints()`.
  It validates, delegates to the ledger, and dispatches an event only for a booking that really happened,
  so a redelivered webhook does not tell the shop twice that points were awarded.

## Points live in batches

`loyalty_point_lot` holds one row per delivery of points: how many arrived (`points`, never changes), how
many are left (`remaining_points`), and when they die (`expires_at`). A balance alone cannot answer
"which points expire next" or "which points did the customer just spend" — the old expiry job had to
compare a total against a total, and the amount it expired was a guess.

- **The invariant is `sum(remaining_points) == points_balance`**, per customer. `LoyaltyPointLots::remaining()`
  exists so a violation is reported (`LoyaltyException::lotBalanceMismatch`) instead of drifting further.
  When you touch anything that writes points, check both sides.
- Spending is FIFO over `(expires_at IS NULL), expires_at, created_at, id`: the batch that dies first is spent
  first, and a batch **without** a date is spent last — a manual adjustment is the only points that never
  disappear, so burning it first is the opposite of what a customer expects.
- `loyalty_point_lot_use` records which batches one booking emptied. It is what a refund needs to put points
  back where they came from: refunding into a fresh batch would hand out a new expiry date nobody decided.
  It also means `LoyaltyLedger` writes the booking **before** the use rows — the foreign key points that way.
- `LoyaltyPointLots` never opens a transaction and never writes the balance. It is the working part of the
  ledger, which holds the account lock; used on its own its `FOR UPDATE` ends with the statement.
  Do not inject it into a subscriber to "quickly read the batches".
- `allocate()` decides, `commit()` writes. The split exists because the booking amount is the *outcome* of
  the allocation while the use rows need the booking to exist — and it leaves the decision testable
  without a database.
- Expiry is one transaction per customer: re-reading the batches under the account lock is what makes a
  second worker see zero left instead of expiring the same points twice. A customer whose batches do not
  add up to their balance is logged and skipped, so one broken account does not keep the others' points alive.
- A batch that is spent to zero keeps its row. "This customer once earned 500 points on that order" is
  history; history that disappears cannot be audited. Do not delete lots.

## Two ways to earn, one rule each

Points come from two facts that look alike and are not: an order was completed, and money was paid into
the wallet. Each has its own rate and its own subscriber, and neither rate is derived from the other.

- `OrderStateChangeLoyaltySubscriber` prices a **completed order** off `order.amountTotal`, which is the
  total *after* the credits — a wallet payment, a points discount. What the balance paid for is not
  counted again.
- `WalletTopUpLoyaltySubscriber` prices a **credited top-up** off the amount that arrived, through
  `core.loyalty.topUpPointsPerCurrencyUnit`. Zero — the default — pays nothing, and that is the honest
  state: a shop that rewards buying rather than prepaying says so in the settings card, not in the code.
- Because the order total excludes the wallet share, wallet money earns exactly once: at the top-up if
  the rate is set, otherwise never. Do not "fix" the order rule to count `amountTotal` plus the wallet
  share - that pays the same money twice.
- The tier multiplier applies to both. It is the promise the tier makes about earning, and it has to
  hold where the money comes in, or a member would be punished for prepaying.
- **Tier points are only earned by orders.** Moving money into the shop is not buying, and a tier must
  not be purchasable by topping up a wallet.
- Both listeners reach the ledger the same way — `LoyaltyPointService::award()` with an idempotency key
  built from the fact (`<orderId>-earned`, `<topUpId>-bonus`). Never book from a subscriber directly;
  a webhook that arrives twice would otherwise pay twice.

The wallet does not know this module exists. It announces `WalletTopUpCreditedEvent` and stops; the
dependency points one way. A failure in the bonus is logged, not thrown: the money is already on the
wallet, and the gateway would answer the retried notification with "already settled", losing the bonus
for good instead of reporting it.

## A return undoes the points

An order touches the account twice, and a return has to undo both - in opposite directions, through
different seams.

- **What the order spent** comes back with the refund. `LoyaltyRefundSource` is one
  `OrderReturnRefundSource` next to the wallet: it speaks in the minor unit of the currency so the
  refund service can split the amount, but it settles its part **in points**, into the batches the
  points left. The gateway never collected that discount - asking it for the money would be refused,
  and paying it out as cash would turn a discount into money.
- **What the order earned** goes when the return reaches `closed`.
  `OrderReturnStateChangeLoyaltySubscriber` listens on the state machine and not on
  `order_return.completed`: that event is only dispatched by the asynchronous refund path, a
  synchronous channel never fires it.
- `LoyaltyReturnPoints` holds both rules and is the only place that knows them. What a point was worth
  is read back from the order itself - the `loyalty-points` credit line item and the redemption
  booking - never from the ladder: the ladder may have changed since the customer spent them, and the
  customer is owed what their points were worth then.
- A partial return settles only what is still out. The refund side is capped by
  `loyalty_point_lot_use.returned_points`, the revoke side by the batches of that order and by the
  share of the goods that came back, so several partial returns add up without anybody counting them.
- A revoke stops where the batches stop. Points the customer already spent are **not** turned into a
  debt - a negative balance is either unspendable or a claim nobody agreed to, and the return is booked
  either way.
- A batch that ran out while the goods were travelling brings nothing back. Giving points into a dead
  batch hands them out already expired; inventing a new date would be a gift nobody decided to give.
- The order of the sources is explicit (wallet `priority: 100`, points none): the money the customer
  put in comes back before the discount is restored, so a partial return does not quietly pay out
  cash as points.

## What a point is worth

The ladder lives in `loyalty_redemption_level` and is read through `LoyaltyRedemptionCalculator`, which
answers with a `LoyaltyRedemptionRates` value object. **Nothing else computes a discount from points.**

- A row is a step, not a range: `(points, value_per_point)` applies from that point count until the next
  step begins. Below the first step the setting `core.loyalty.pointValueInCurrency` applies.
- The steps are read as **bands**: 5000 points pay the first thousand at the base rate and only what goes
  above a threshold at the better one. Switching the rate for the whole amount instead is what makes
  4999 points worth 50.00 next to 5000 points worth 75.00 — a cliff customers complain about.
- `amountFor()` and `maxPointsFor()` are the two directions of the same walk over the same bands, which is
  why they cannot disagree. Do not "optimise" the inverse into one division: the rate changes with the
  number of points, so a division is only right inside one band.
- An empty table is not an error: it means one rate for everything, which is exactly what the shop did
  before the ladder existed. That is why the migration seeds nothing.
- Amounts are not rounded in the rates object. Rounding belongs to the price calculator that knows the
  currency exponent; rounding earlier makes the amount and the points it came from disagree.
- The ladder is read on every call, not cached in the service. A few rows and one indexed read are cheap;
  a cache on a shared service would survive an admin edit inside a running worker.

## What a tier grants

`loyalty_tier_benefit` holds one row per benefit type a tier grants: `type` names the rule, `value`
carries its single parameter, `position` is the display order. The types and the meaning of their value
are in `LoyaltyBenefitTypes` — `earn_multiplier` (factor), `cart_discount` (percent),
`free_shipping` (`1`), `birthday_gift` (amount), `points_expiry_days` (days).

**`LoyaltyBenefitResolver` is the only way to read them.** It answers with a `LoyaltyTierBenefits`
value object, so no consumer filters a collection or knows that an empty table means "no bonus".
The order subscriber asks it for a multiplier, the storefront route for everything to print.

- One row per type is a `UNIQUE(loyalty_tier_id, type)`. Two `earn_multiplier` rows would raise the
  question which wins; the schema refuses to represent the question.
- An absent benefit is a **complete answer**, not a placeholder: no `earn_multiplier` row means the
  effective multiplier is `1.0`, no `cart_discount` row means `0.0`. Accessors answer the effective
  value so callers never null check. Do not "default" a missing row anywhere else.
- Two writers would be a bug, but two *sources* are not: `points_multiplier`, `discount_percent` and
  `free_shipping` are still read as a fallback while the administration form writes them. The
  resolver merges row first, column as gap filler, and the fallback is deleted together with the
  columns on the day the form and the storefront template read `benefits`. Do not add a fourth column
  to that fallback — add a benefit type.
- A tier loaded with `benefits` association costs no extra query; a tier loaded without one gets its
  benefits from the repository. Both paths produce the same object, which is why the route can load
  all tiers at once.

## Vocabulary


One concept, one word — these three modules share the contract:

| Concept | Word | Never |
|---|---|---|
| a customer's point account | loyalty account (`LoyaltyAccount`, `LoyaltyAccountService`, table `loyalty_account`) | membership, profile |
| the level a customer reached | tier (`LoyaltyTier`, `TierResolver`) | level as a class name, rank, grade |
| the integer sort position of a tier | level (`LoyaltyTierEntity::getLevel()`) | — |
| the points that decide the tier | tier points (`tier_points`, `min_tier_points` on the tier) | growth, experience |
| the tier a customer is on right now | tier (`tier_id`) — bought or earned | membership tier, only the paid one is a membership |
| the day a paid membership runs out | membership paid until (`membership_paid_until`) | valid until, the tier is not what expires |
| points | points (`pointsBalance`, `bookPoints()`) | credit, score, coin |
| a paid plan | plan (`MembershipPlan`) | package, product |

`LoyaltyTier` is the name the industry uses (Salesforce `LoyaltyTier`, airline `tier points`), not a translation
of "level". Renaming it would only make the module harder to read for anyone who knows a loyalty system.

Three names still carry words that no longer match the columns, and that is deliberate:

- twig blocks `sw_customer_detail_points_growth`, `sw_customer_detail_points_membership_valid_until` and
  `sw_loyalty_tier_detail_content_card_min_growth_value` — a block name is where a theme or plugin hooks in.
  Renaming one removes overrides that shops already wrote, and it removes them **silently**. Keep the block
  name, name the variable inside it correctly.
- ACL privileges `loyalty.viewer` / `creator` / `editor` / `deleter` — they are referenced by granted roles,
  so a rename has to migrate permissions too. Nothing about the domain changes if the privilege keeps its word.
- the `loyalty_tier` entity — it is the tier itself, not the account. `loyalty_tier` next to `loyalty_account`
  reads unambiguously, and renaming it would churn every translation key and twig block on the tier pages.

`loyalty_point_transaction` and `membership_order` still use the pre-rename words; that part of the rename
is pending (see the architecture note, §20.7). Do not "fix" one of them in passing — both are table renames
with index and foreign-key renames attached, and half of one is worse than none.

## Where a new rule goes

1. A new way to earn or spend points → a method on `LoyaltyPointService` that calls `LoyaltyLedger`.
   Do not reach for the repository from a subscriber; the ledger is what keeps the balance consistent.
2. A new field on the account → migration in `src/Core/Migration/V6_7/` **and** `LoyaltyAccountDefinition`
   **and** `LoyaltyAccountEntity` **and** `LoyaltyLedger::toAccount()` when the ledger lock has to read it.
3. A new tier property → `LoyaltyTierDefinition`; check `TierResolver` and the admin translation aggregate.
4. A new benefit a tier can grant → a constant in `LoyaltyBenefitTypes`, an accessor on
   `LoyaltyTierBenefits`, and a document comment saying what its `value` means. No migration, no new
   column — that is the point of the table.
5. A new way to add points → `bookPoints()` with a positive amount and the expiry date it should have.
   The batch is opened for you. Never insert into `loyalty_point_lot` directly: the balance and the
   batches would drift apart and nothing would report it.
6. Points are integers. Never introduce a float for points — the ledger compares balances for exact zero.
7. A new kind of booking → a constant in `LoyaltyPointTransactionTypes` with a document comment saying
   which direction it moves and who writes it. The value is persisted; renaming one turns every booking
   already written into an unknown type.
8. A new thing a return does to the points → a method on `LoyaltyReturnPoints`, wired either into the
   refund (if it settles value and must happen once per return) or into the closed-return subscriber
   (if it is a consequence of the goods coming back). Do not put money arithmetic into a subscriber.
9. A new source of earning → a listener on the fact that earns, a rate of its own in `LoyaltySettings`
   and its own field in `loyalty.xml`, and an idempotency key built from that fact. Do not reuse an
   existing rate: two sources that share one number cannot be priced differently later, and the third
   one will arrive.
10. When a third earning source appears, the arithmetic of the two that exist (`amount × rate ×
    multiplier`, floored) moves into one rule class. Two copies are a pattern; three are a bug.

## What is public API and what is not

Public: `LoyaltyPointService` (`award` / `redeem` / `adjust` / `addTierPoints`), `LoyaltySettings`,
`LoyaltyAccountService`, `LoyaltyBenefitResolver`, `TierResolver`, `LoyaltyRedemptionCalculator` with
`LoyaltyRedemptionRates`, `LoyaltyTierBenefits`, the entities and definitions, the store API routes with their
`*RouteExtension`, and the events.

`@internal`: `LoyaltyLedger` and `LoyaltyPointLots`.

The test is not "does it feel internal" but "does a third party have a different, legitimate thing to say here".
Nobody has a legitimate opinion about the order of the three writes, about the row lock, or about which batch pays —
and the three are one mechanism: vary one and `sum(remaining_points) == points_balance` stops holding, quietly.
Everybody who wants points to move *does* have something to say, and `LoyaltyPointService` is where that belongs.
A booking kind that service cannot express is an extension point to add **there**; it is not a reason to call the
ledger or insert batches directly.

Two mechanics worth knowing: the marker is read from the **class** docblock only, so a `@internal` on `__construct`
means "we build it", not "do not use it" (that is why almost every service here has one and still counts as public);
and `@internal` is not combined with `@final` — the stronger marker wins. `InternalClassRule` enforces the parts that
are mechanical (test classes, storefront controllers, subscribers, task handlers, migrations, message handlers).

## Gates

- `vendor/bin/phpunit --testsuite unit --filter "Checkout\\\\Loyalty"` and `--testsuite integration --filter "Core\\\\Checkout\\\\Loyalty"`.
- `vendor/bin/phpunit --testsuite migration --filter "Migration1793606"` — the batch backfill is the part that
  has to be right: a balance without batches is unspendable, so the copy is covered by its own tests.
- A change to the return handling also needs `--testsuite integration --filter "Core\\\\Checkout\\\\OrderReturn"`:
  the points program is one source of the refund, and the split only works if both sides agree on the unit.
- `bin/console dal:validate` — the module must not add entries.
- After touching a definition: clear `var/cache/dev_*` first, the compiled container keeps the old fields.
  A newly registered definition reports `Table "..." has no configured definition` until the cache is cleared.
- The test database is shared with every other session on this machine. Integration tests here create tiers
  and customers and do **not** clean up, so their unique keys are derived from a uuid: a fixed
  `technical_name` or `customer_number` makes the second run fail on a duplicate key and then cascades
  (`SAVEPOINT DOCTRINE_n does not exist`) into every later test. When a suite fails that way, run it once
  against a private database: `DATABASE_URL=<url pointing at another schema> vendor/bin/phpunit ...`.
