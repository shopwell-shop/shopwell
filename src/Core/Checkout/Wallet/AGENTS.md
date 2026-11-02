# `src/Core/Checkout/Wallet` — architecture guide

Stored value: a customer tops the wallet up with real money and pays orders from the balance.
One wallet per customer **and currency** (`UNIQUE(customer_id, currency_id)`), one ledger per wallet.

## Layers

```
Api/              admin endpoints: adjust and top up on behalf of a customer
Cart/             subtracts the requested amount from the cart total
Entity/           wallet, its transactions, the top-up rows
Event/            balance changed, top-up credited
Extension/        Store API route extensions - kept at the module root, like Order/, Payment/, Gateway/
Purchase/         how a paid top-up is handed to the shared settlement kernel
SalesChannel/     `/wallet`, `/wallet/payment`, `/wallet/top-up`, `/wallet/top-up/status`
Service/          account, ledger, payment (consume / refund), adjustment (top-up / adjust), top-up lifecycle
Subscriber/       account provisioning on customer write, order placed -> debit, top-up paid -> credit
```

## The balance is a projection

`customer_wallet.balance` is derived from `customer_wallet_transaction`.
It is written **only** by `WalletLedger::book()`, which locks the wallet row (`Ledger\RowLock`), writes the
transaction and moves the balance inside the same database transaction.

- `WalletLedger` — the write side of the ledger. Every booking carries an idempotency key; `oncePerOrder`
  makes a repeated booking for the same order a no-op instead of a second debit.
- `WalletAccountService` — `wallet()`, `balance()`, `open()`. `open()` is idempotent: losing the creation race is
  success, and no other class may `create()` a wallet (the unique key would reject it).
- `WalletPaymentService` — `debitForOrder()` / `refundForOrder()`. Both are `oncePerOrder`:
  a refund that can be booked twice is a money leak, not a rounding error.
- `WalletAdjustmentService` — `topUp()` / `adjust()` from the admin; validates the minimum, refuses a negative balance.
- `WalletTopUpService` — the top-up lifecycle (`options()`, `create()`, `recentTopUps()`, `find()`).
  It never credits; crediting belongs to settlement.

## Settlement is shared, not owned

A paid top-up is one implementation of
`Shopwell\Core\Checkout\Payment\Storefront\Settlement\SettleablePurchase`:

- `WalletTopUpPurchase::settle()` books the credit through `WalletLedger` with `externalReference` = the purchase id
  and moves the row to `credited`. Idempotency sits in `isSettled()` (the row state), not in `isSettleable()`:
  the settlement short-circuits on a row that is already `credited`.
- The `attach` marker that ties a gateway payment back to the top-up is built and parsed by `PurchaseAttach`
  (`wallet_top_up:<id>` from `shopwell.wallet.purchase_attach`). Never concatenate or `substr()` it by hand —
  a marker that does not round-trip credits nothing, silently.
- `WalletTopUpSettlementSubscriber` listens for a finished payment and calls `PurchaseSettlement`.

## A credited top-up is announced, the reward is not decided here

`WalletTopUpCreditedEvent` says a top-up became money: who paid, how much, in which currency, and which
top-up. It is dispatched **after** the settlement committed, and only when that settlement really
credited the row — a repeated notification of the same payment announces nothing. That is the same rule
the ledger follows when it announces a balance change after its transaction: a listener must never act
on something that can still be rolled back.

What the credit is worth to anybody else is not the wallet's question. The wallet has no idea that a
loyalty program exists and must not learn: `Loyalty\Subscriber\WalletTopUpLoyaltySubscriber` prices the
top-up into points, and it is the only place that does. **The dependency runs one way — loyalty reads
wallet facts, never the other way round.** A shop without a loyalty program runs the wallet unchanged.

`WalletTopUpCreditedEvent` is deliberately not `WalletBalanceChangedEvent`: the latter says the balance
moved, which also happens on every payment and refund, and it carries no top-up a listener could make
its work idempotent with. Subscribe to the fact, not to something that coincides with it.

## Money representation

`amount` and `balance` are `DOUBLE` in the schema, inherited from the first version of these tables.
New code must not add more float arithmetic:

- reading a balance into a calculation → convert once, at the boundary;
- sending an amount to a gateway → `Money::fromFloat()` (`System/Payment/Gateway/Request/Money`), which formats by
  the currency exponent and converts to minor units;
- comparing amounts → compare minor units, never floats.

Moving these columns to integer minor units is planned; until then, keep every new computation in integers.

## Where a new capability goes

1. A new way to move money → a method on `WalletPaymentService` / `WalletAdjustmentService` that calls the ledger
   with a `WalletTransactionTypes` constant. Add the constant there, not as a string literal in the caller.
2. A new column on the wallet → migration **and** `CustomerWalletDefinition` **and** `CustomerWalletEntity`
   **and** `WalletLedger::toAccount()` when the lock has to read it.
   Dropping or changing a column means editing the original creation migration — this codebase is a development
   baseline without backward compatibility, so there is no destructive migration for it.
3. A new Store API field → `WalletPresenter`, not the route.
4. Currency handling: an unspecified currency means `Defaults::CURRENCY`; never assume the wallet is unique per
   customer — look it up by `(customerId, currencyId)`.

## Gates

- `vendor/bin/phpunit --testsuite unit --filter "Checkout\\\\(Wallet|Ledger)"` and
  `--testsuite integration --filter "Core\\\\Checkout\\\\Wallet"`.
- `bin/console dal:validate` — a column without a field (or the reverse) is a hard failure here.
- After moving or renaming a class: `composer dump-autoload`, then run PHPStan. Unit tests do not load controllers
  and routes, and a stale `$this->fieldName` in either of them survives a green suite.
