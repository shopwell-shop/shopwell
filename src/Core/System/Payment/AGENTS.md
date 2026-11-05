
# `src/Core/System/Payment` — architecture guide

Payment infrastructure for collecting money, refunds, payouts and recurring charges.
`Checkout/Payment` adapts shop orders and storefront purchases to this module; the infrastructure never depends on checkout-specific purchase types.

## Model

- A **method** is what the payer recognizes, such as Alipay or WeChat Pay.
  `Method/PaymentMethod` declares its open technical identifier, translation key and capabilities.
- A **provider** implements a payment protocol and declares its products, credential schema and actions.
  An official integration and an aggregator can offer the same logical method.
- A **product** is an executable provider offering, such as mobile website payment or mini-program payment.
  `Product/PaymentProduct` declares its method, supported scenes, interactions, currencies, runtime inputs and capabilities.
- An **account** identifies a merchant with a provider and owns encrypted credentials, enabled products and product parameters.
  `App/Aggregate/PaymentAppAccount` grants an application access to an explicit account and assigns routing priority.
- A **cashier configuration** belongs to an application and defines an entry point's method allowlist, routing policy and presentation.
  A cashier session freezes a business obligation, configuration and caller-supplied execution context before payment starts.
- An **order** is the business obligation.
  An **attempt** freezes the selected account, method, provider, product, interaction and provider request identity.
  Transactions audit provider calls; they do not replace attempts.

Identifiers for methods, providers, products, scenes and interactions are extensible strings.
Core constants describe built-in integrations without closing the catalog to extensions.
Scene descriptors contain data; ecosystem-specific scene declarations belong to integrations or entry-point adapters.
Browser detection describes the current browser and proves neither native SDK support nor an account-scoped payer identity.

## Responsibilities

```text
Api/              HTTP parsing, app authentication and response presentation
App/              application lifecycle, signing, access limits and account grants
Account/          merchant account lifecycle, credentials and identity protection
Method/           payer-facing method definitions
Product/          provider product declarations and scene contracts
Routing/          eligibility, authorized candidates and routing policy
Cashier/          cashier configurations, immutable sessions and payment initiation
Order/            business obligations, execution attempts, refunds and reconciliation
Transfer/         recipient onboarding and payouts
Subscription/     plans, mandates, subscriptions and billing cycles
Provider/         provider declarations, configuration and runtime gateway assembly
Integration/      provider-specific protocol adapters
Gateway/          Payum orchestration and normalized request/result contracts
Notification/     authenticated provider callbacks entering the payment domain
Webhook/          signed application notifications leaving the payment domain
```

HTTP controllers translate requests; they do not choose provider products or mutate financial state directly.
Manage account configuration through `Account/PaymentAccountService`; applications grant existing accounts without writing merchant credentials.
Routing intersects active accounts, account-enabled products and application product grants, then delegates candidate choice to a routing policy.
Provider actions consume normalized gateway models and results.
Vendor SDK calls, request models, cryptography and wire-response parsing belong in the integration's `Api/` package.

## Extension contracts

1. Register logical methods with `shopwell.payment.method_definition` and a translation key.
2. Add a provider under `Integration/<Name>/`, extending `AbstractPaymentProvider`.
   Keep protocol actions in `Action/`, SDK access in `Api/`, and notification verification in `Notification/`.
3. Declare products through `getProducts()` with the offered logical method and actual supported capabilities.
   A capture action must produce an interaction that its product declares.
4. Use `Product/Event/PaymentProductsEvent` to contribute products or scene support when extending an existing declaration.
5. Register custom routing policies with `shopwell.payment.routing_policy`.
   Policies can choose only from the authorized candidates they receive.
6. Run `bin/console payment:providers:validate` to validate the complete declaration graph.

A new provider, payment method or business scene must not require a new central conditional branch.
Use events where timing fits; introduce a strategy or registry when the behavior itself varies.
Labels are snippet keys or translated at presentation boundaries.
Do not duplicate provider-specific identifiers in checkout or wallet code to select products.

## Financial invariants

- Store payment amounts as integer minor units with their currency.
  Convert external decimal or shop float amounts at the boundary through `System/Currency/CurrencyAmountConverter`.
- Authorize every new route against the application's account grants and enabled products.
  Required payer identities are scoped to the selected account; a common OpenID cannot authorize another merchant application's product.
- Serialize merchant identity updates and first financial bindings through `Account/PaymentAccountLock`.
  Revalidate the resolved account configuration inside the binding transaction with a current read.
- Claim an attempt and persist its stable provider request identity before network I/O.
  Keep network calls outside database row locks.
- Replay a persisted handover.
  An unanswered or unknown attempt must be reconciled before another provider trade can begin.
- Bind refunds, queries and closes to their original attempt and account.
  Account deactivation or changes to routing priority affect new payments, not historical recovery.
- Treat provider callbacks as authenticated inputs to shared state handlers.
  Preserve early settlement when the provider responds after its callback has already arrived.
- Persist financial state and its event outbox atomically.
  Dispatch external application notifications after commit, with idempotent delivery and retries.
- Return a retryable server response when callback processing fails transiently.
  Do not acknowledge an infrastructure failure as successfully processed.

## Style and validation

Follow the root `AGENTS.md`, PHP and Administration skills, and the established patterns in `Checkout/Cart` and `Checkout/DocumentV2`.
Use domain-prefixed class names and role suffixes such as `Entity`, `Collection`, `Registry`, `Factory`, `Presenter`, `Reconciler` and `Verifier`.
Keep entities focused on DAL data and put business transitions in their responsible service or state handler.
Validate untrusted payloads at boundaries; avoid fallback chains that conceal broken internal contracts.
This is a development baseline: edit original schema migrations and remove obsolete contracts without compatibility aliases or deprecated branches.

Run payment unit and integration suites, consumer suites for the touched checkout, wallet, membership and storefront paths, and affected migration tests.
After class moves, run `PaymentNamespaceAuditTest`, `StaleClassReferenceTest`, autoload generation and container lint.
Lint every touched file and run PHPStan for changed PHP source and tests.
Administration behavior changes require DOM-oriented Jest coverage; verify rendered layouts after rebuilding the Administration assets.
