# `src/Core/System/Payment` — architecture guide

This module is the platform's payment gateway for external systems: apps call `/payment-api/v1/*` to collect money,
the platform talks to payment interfaces (Alipay, WeChat Pay, PayPal, Stripe) on their behalf, books what happened
and reports it back through signed webhooks.
It is **independent of the checkout** — `Checkout/Payment` is the shop's own cashier and must not be mixed with this module.

## Layers

```
Api/              HTTP contract of the gateway: controllers, request/response structs, route scope
App/              the apps that call us: authentication, provisioning, credential sealing
Order/            collection domain: payment order aggregate, state, capture, close, reconcile, refunds
Transfer/         payout domain (money leaving the platform)
Subscription/     recurring domain (plans, mandates, cycles)
Provider/         declaration contract: what an interface is and what it can do
Integration/      protocol adapters: one package per interface, following the payum package layout
Gateway/          payum orchestration: base action/api, gateway factory, request models
Notification/     inbound: interface -> platform (verified, never a source of state)
Webhook/          outbound: platform -> app (signed, retried)
```

Dependency rule: `Api/` → domain (`Order/`, `Transfer/`, `Subscription/`) → channel (`Provider/`, `Integration/`, `Gateway/`).
The channel layer never depends on the domain: an action reads the request and `PaymentGatewayModel`, nothing else.
`Notification/` is the second inbound path into the domain (the first is `Api/`); both end in the same
`PaymentOrderPaidHandler`, which is the only place a payment becomes paid.

Direction in one line: **Notification comes in, Webhook goes out.**

## Naming

Names follow the conventions of the surrounding modules — look at `Checkout/Order`, `Checkout/Payment`,
`Framework/App` and `System/StateMachine` before inventing a new one.

- Directories and namespaces are the same path. A move is a rename; there is no intermediate state.
- Class names keep their domain prefix (`PaymentOrderService`, not `OrderService`) — nested domains do not strip it.
- Suffixes carry the role: `*Entity` / `*Collection` (data only, no behaviour), `*Service` (write side of an aggregate),
  `*StateHandler` (the only place state machine actions are named), `*States` (state machine + action constants),
  `*Registry` (read side, never writes), `*Factory` (builds a runtime object), `*Presenter` (domain → response shape),
  `*Reconciler` (brings local state in line with the channel), `*Extractor` (reads references out of an inbound payload),
  `*Verifier` (proves a notification is genuine).
- An interface package has exactly one layout, copied from payum: `Action/`, `Api/`, `Request/`, `Notification/`,
  and its `<Name>Provider.php` at the root. Do not add a sixth kind of file to that root.
- A class that talks to a channel is `*Action`; a class that talks to the admin is a controller under `Api/`.

## Adding a payment interface

One class plus its actions, no core file and no migration:

1. `Integration/<Name>/` with `Action/`, `Api/`, `Request/`, `Notification/`.
2. `<Name>Provider extends AbstractPaymentProvider`: technical name, label snippet key, api class, actions,
   notification extractor, credentials, channels, capabilities.
3. Register the services in `src/Core/System/DependencyInjection/payment.php` and tag the declaration with
   `shopwell.payment.provider` (`key` = technical name, `priority` = `-100` for core interfaces).
4. `bin/console payment:providers:validate` must pass; it refuses incoherent declarations.

Capabilities are a contract, not documentation: a request capability must have an action that declares it,
a policy capability is declared explicitly, and the declaration and implementation are checked against each other.

## Where the protocol lives

`Api/` owns the wire: it builds the request objects of the vendor sdk, performs the call, and normalizes the
answer into the array the action reads. An action composes the business content, decides what a rejection means,
and maps the answer onto the domain result — it never reaches for the sdk itself.

```php
// Api: the only place the vendor sdk is touched
public function createOrder(array $order): array   // $answer['error'] carries a rejection, never throws

// Action: business content in, domain result out
$answer = $api->createOrder([...]);
if (isset($answer['error'])) {
    throw PaymentException::gatewayError($api->channel(), PaypalApi::rejectionMessageOf($answer) ?? '...');
}
```

Rules that follow from it:

- One `*Api` method per interface resource, all four interfaces spelling it the same way:
  `createPayment` / `createTransfer` / `createOrder` / `createCheckoutSession` / `createPayout` for a creation,
  `query` / `queryTransfer` for a read, `close` for a close, `refundPayment` / `refundCapture` / `createRefund` for a refund.
  A verb that only one interface knows (`precreate`, `verifyWebhookSignature`) stays in that interface's api —
  but a second interface using the same verb must not invent a different word for it.
- Two kinds of answer, and which one a method returns is a decision, not a habit:
  an answer that is the same fact for every interface (`query`, `close`) is narrowed to a `QueryResult` / `CloseResult`
  on the spot; an answer only the caller can read is handed back as `array<string, mixed>` in the shape of the wire.
- A rejection of the interface is an **answer**, not an exception: return it with `error.code` / `error.message`
  and the original body under `raw`. Only a failure that produced no answer at all may throw. A capture that was
  refused is an empty answer for the action to read (`$answer['qr_code'] ?? null`), because "refused" and "retry"
  mean different things per channel and that is the action's call.
- Reading a rejection is shared, not copied: `<Name>Api::rejectionCodeOf()` / `rejectionMessageOf()`.
  Do not parse `$answer['error']` in an action — three copies of that parse is how the shapes drift apart.
- Reading a wire field the domain needs (an intent id, an approve link, a signature) belongs to the api as well:
  `StripeApi::paymentIntentIdOf()`, `PaypalApi::approveUrlOf()`, `AlipayApi::verifyNotificationSignature()`.
- payum orchestration stays in `Gateway/`; the vendor sdk never appears in `Action/` — not even a request model.
  A model the sdk wants is built in the `*Api` from the business content the action records, so the record and the
  call cannot tell different stories.

## Style

- Match the module you are in. If a pattern exists in `Checkout/Cart` or `System/StateMachine`, use it instead of a new one.
- Validate at the boundary, not everywhere. A guard is justified when the value really can arrive in the wrong shape
  from outside (HTTP payload, channel response, database row). Re-checking a value that the type system or the caller
  already guarantees (`is_string($name) && $name !== ''` before passing a `string` into a place that needs a name)
  hides the real invariant and reads as distrust of our own code. Look at how `Checkout/Order` handles its ids.
- No defensive `??` chains that turn a programming error into a silent default. If a field is required, make it required.
- Comments explain the business reason, not the syntax. The existing files are the reference.
- Amounts are integers in the currency's smallest unit. Never format money inside the channel layer.

## Gates

- `vendor/bin/phpunit tests/unit/Core/System/Payment` and `tests/integration/Core/System/Payment` must stay green.
- A class move is not verified by the module's own suite. Run the suites of the **consumers** as well —
  `--testsuite migration`, `tests/unit/Core/Checkout/Payment`, `tests/unit/Core/Checkout/OrderReturn` —
  because a suite the pipeline does not run keeps a dead import for as long as nothing executes that line.
  `PaymentNamespaceAuditTest` reads the whole repository for that reason: every reference to a class of this module
  must name a file that exists. It is the mechanical check; the suite matrix above is the behavioural one.
- A module-scoped audit only sees its own namespace. `tests/unit/Core/DevOps/StaticAnalyze/StaleClassReferenceTest`
  is the repository-wide counterpart — every class that `src` imports or names statically has to exist on disk.
  Run it after any move, no matter which module moved: the references that break usually live in a definition
  of another domain (`System/Currency`, `System/Language`, `Checkout/Customer`).
- `vendor/bin/phpstan analyse` — clear the result cache first after moving files.
- `vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.dist.php <changed files>`.
- `bin/console lint:container` after touching the DI file.
- After moving a class: `composer dump-autoload`, then drop the test cache (`var/cache/test_*`), otherwise the
  compiled container keeps loading the old class names.
